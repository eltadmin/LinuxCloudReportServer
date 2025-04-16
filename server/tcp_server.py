"""
TCP Server Module for the Linux Cloud Report Server.

This module provides the TCP server implementation that handles client connections
and commands with encryption.
"""

import asyncio
import logging
from typing import Dict, Optional, List, Tuple, Union
import json
import zlib
from datetime import datetime, timedelta
from pathlib import Path
import random
import string
import traceback
from .constants import CRYPTO_DICTIONARY, DEFAULT_SERVER_KEY, SPECIAL_KEYS, KEY_LENGTH_BY_ID
from .crypto import DataCompressor, generate_crypto_key
from .key_manager import KeyManager
from .message_handler import MessageHandler
import base64
import re
import socket
import os
import struct

logger = logging.getLogger(__name__)

# Configuration constants
DEBUG_MODE = False  # Set to False for production
DEBUG_SERVER_KEY = "F156"  # Match Go implementation's key
USE_FIXED_DEBUG_RESPONSE = True  # Use a fixed format response for INIT command
USE_FIXED_DEBUG_KEY = True  # Use a fixed crypto key for encryption/decryption tests

# Command constants
CMD_INIT = 'INIT'
CMD_ERRL = 'ERRL'
CMD_PING = 'PING'
CMD_INFO = 'INFO'
CMD_VERS = 'VERS'
CMD_DWNL = 'DWNL'
CMD_GREQ = 'GREQ'
CMD_SRSP = 'SRSP'

# Response format constants - only keep the standard format that matches Go server
RESPONSE_FORMATS = {
    'standard': "200-KEY={}\r\n200 LEN={}\r\n",  # Format matching Go TCP server implementation
}

# Timeout constants (seconds)
CONNECTION_TIMEOUT = 300  # 5 minutes
PENDING_CONNECTION_TIMEOUT = 120  # 2 minutes
INACTIVITY_CHECK_INTERVAL = 60  # 1 minute

# Key generation constants
KEY_LENGTH = 4  # Fixed length for reliability

class TCPConnection:
    """
    Represents a TCP connection to a client with encryption capabilities.
    
    This class handles the communication with a single client, including
    encryption and decryption of messages, and connection state tracking.
    """
    
    def __init__(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
        """
        Initialize a new TCP connection with the given reader and writer.
        
        Args:
            reader: The asyncio stream reader for this connection
            writer: The asyncio stream writer for this connection
        """
        self.reader = reader
        self.writer = writer
        self.client_id: Optional[str] = None
        self.authenticated = False
        self.initialized = False
        self.peer = writer.get_extra_info('peername')
        self.last_ping = datetime.now()
        self.client_host = self._extract_host_from_peer()
        self.app_type = None
        self.app_version = None
        self.server_key = None
        self.key_length = None
        self.crypto_key = None
        self.last_error = None
        self.last_activity = datetime.now()
        self.connection_time = datetime.now()
    
    def _extract_host_from_peer(self) -> str:
        """Extract the host IP from the peer info."""
        if self.peer and len(self.peer) >= 1:
            return str(self.peer[0])
        return "127.0.0.1"  # Default to localhost if not available
    
    def set_crypto_key(self, crypto_key: str) -> None:
        """Set the crypto key for this connection."""
        self.crypto_key = crypto_key
        logger.debug(f"Set crypto key for connection: {crypto_key}")
    
    def encrypt_data(self, data: str) -> Optional[str]:
        """
        Encrypt data using the negotiated crypto key.
        
        Args:
            data: The string data to encrypt
            
        Returns:
            The encrypted data as a string, or None if encryption fails
        """
        if not self.crypto_key:
            self.last_error = "Crypto key is not negotiated"
            return None
            
        compressor = DataCompressor(self.crypto_key)
        result = compressor.compress_data(data)
        
        if not result:
            self.last_error = compressor.last_error
            return None
            
        return result
        
    def decrypt_data(self, data: str) -> Optional[str]:
        """
        Decrypt data using the negotiated crypto key.
        
        Args:
            data: The encrypted data string
            
        Returns:
            The decrypted data as a string, or None if decryption fails
        """
        if not self.crypto_key:
            self.last_error = "Crypto key is not negotiated"
            return None
            
        compressor = DataCompressor(self.crypto_key)
        result = compressor.decompress_data(data)
        
        if not result:
            self.last_error = compressor.last_error
            return None
            
        return result

    def update_activity(self):
        """Update the last activity timestamp to the current time."""
        self.last_activity = datetime.now()
        
    def test_encryption(self) -> bool:
        """
        Test the encryption with known test strings to verify it works correctly.
        
        Returns:
            True if encryption test is successful, False otherwise
        """
        if not self.crypto_key:
            self.last_error = "Crypto key is not set for testing"
            return False
            
        try:
            # Test with different test strings
            test_strings = [
                "TT=Test",     # Standard Delphi test string
                "Test123",     # Simple ASCII text
                "ID=123\r\nTT=Testing"  # Data format similar to client
            ]
            
            for test_string in test_strings:
                logger.debug(f"Testing encryption with key '{self.crypto_key}' and test string '{test_string}'")
                
                # Attempt to encrypt and decrypt
                encrypted = self.encrypt_data(test_string)
                if not encrypted:
                    logger.warning(f"Failed to encrypt test data: {test_string}")
                    continue
                
                logger.debug(f"Encrypted data (base64): '{encrypted}'")
                
                decrypted = self.decrypt_data(encrypted)
                if not decrypted:
                    logger.warning(f"Failed to decrypt test data for '{test_string}'")
                    continue
                
                logger.debug(f"Decrypted data: '{decrypted}'")
                
                # Check the result
                if test_string == decrypted:
                    logger.info(f"Encryption test passed with '{test_string}'")
                    return True
                elif "TT" in test_string and "TT" in decrypted and "Test" in decrypted:
                    logger.info(f"Encryption test passed with partial match for '{test_string}'")
                    return True
            
            # If none of the tests succeeded
            self.last_error = "All encryption tests failed"
            logger.error(self.last_error)
            return False
            
        except Exception as e:
            self.last_error = f"Encryption test failed: {str(e)}"
            logger.error(f"Exception during encryption test: {e}", exc_info=True)
            return False

    async def close(self):
        """Close the connection gracefully."""
        try:
            self.writer.close()
            await self.writer.wait_closed()
            logger.debug(f"Connection to {self.peer} closed successfully")
        except Exception as e:
            logger.error(f"Error closing connection to {self.peer}: {e}")
    
    def __str__(self) -> str:
        """Return a string representation of the connection."""
        return f"TCPConnection(peer={self.peer}, client_id={self.client_id}, authenticated={self.authenticated})"

class TCPServer:
    """
    TCP Server implementation for the Linux Cloud Report Server.
    
    This class implements the server logic for handling client connections and commands.
    """
    
    def __init__(self, report_server=None):
        """
        Initialize the TCP Server.
        
        Args:
            report_server: The main report server instance (optional)
        """
        self.report_server = report_server
        self.connections: Dict[str, TCPConnection] = {}
        self.server = None
        self.running = False
        self.host = None
        self.port = None
        self.key_manager = KeyManager()
        self.message_handler = MessageHandler()
        logger.info("TCP Server initialized")
    
    async def start(self, host: str, port: int):
        """
        Start the TCP server on the specified host and port.
        
        Args:
            host: The host IP to bind to
            port: The port to listen on
        """
        self.host = host
        self.port = port
        
        try:
            self.server = await asyncio.start_server(
                self.handle_connection,
                host,
                port,
                reuse_address=True,
                reuse_port=True
            )
            
            addr = self.server.sockets[0].getsockname()
            logger.info(f"TCP Server started on {addr[0]}:{addr[1]}")
            
            self.running = True
            
            # Start the inactive connection checker
            asyncio.create_task(self.check_inactive_connections())
            
            # Run the server
            async with self.server:
                await self.server.serve_forever()
                
        except Exception as e:
            logger.error(f"Error starting TCP server: {e}", exc_info=True)
            raise
    
    async def stop(self):
        """Stop the TCP server."""
        if self.server:
            try:
                self.running = False
                logger.info("Stopping TCP server...")
                
                # Close all connections
                await self._close_all_connections()
                
                # Close the server
                self.server.close()
                await self.server.wait_closed()
                
                logger.info("TCP Server stopped")
            except Exception as e:
                logger.error(f"Error stopping TCP server: {e}", exc_info=True)
        else:
            logger.warning("Cannot stop server: not running")
    
    async def _close_all_connections(self):
        """Close all active connections."""
        if not self.connections:
            logger.debug("No connections to close")
            return
            
        logger.info(f"Closing {len(self.connections)} active connections")
        close_tasks = []
        
        for client_id, conn in list(self.connections.items()):
            try:
                logger.debug(f"Closing connection for client {client_id} at {conn.peer}")
                close_tasks.append(asyncio.create_task(conn.close()))
            except Exception as e:
                logger.error(f"Error closing connection for client {client_id}: {e}")
        
        if close_tasks:
            try:
                await asyncio.gather(*close_tasks, return_exceptions=True)
            except Exception as e:
                logger.error(f"Error in connection close gather: {e}")
        
        self.connections.clear()
        logger.info("All connections closed")
    
    async def check_inactive_connections(self):
        """Periodically check and close inactive connections."""
        logger.info("Starting inactive connection checker")
        
        while self.running:
            try:
                now = datetime.now()
                
                # Check each connection for inactivity
                for client_id, conn in list(self.connections.items()):
                    try:
                        idle_time = (now - conn.last_activity).total_seconds()
                        
                        # If connection has been idle for too long, close it
                        if idle_time > CONNECTION_TIMEOUT:
                            logger.info(f"Closing inactive connection for client {client_id} "
                                        f"(idle for {idle_time:.1f} seconds)")
                            asyncio.create_task(self._cleanup_connection(conn))
                            self.connections.pop(client_id, None)
                    except Exception as e:
                        logger.error(f"Error checking connection {client_id}: {e}")
                
                # Wait for the next check interval
                await asyncio.sleep(INACTIVITY_CHECK_INTERVAL)
                
            except asyncio.CancelledError:
                logger.info("Inactive connection checker cancelled")
                break
            except Exception as e:
                logger.error(f"Error in inactive connection checker: {e}", exc_info=True)
                await asyncio.sleep(INACTIVITY_CHECK_INTERVAL)
        
        logger.info("Inactive connection checker stopped")
    
    async def handle_connection(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
        """
        Handle a new client connection.
        
        Args:
            reader: The asyncio stream reader for this connection
            writer: The asyncio stream writer for this connection
        """
        # Create a new connection instance
        conn = TCPConnection(reader, writer)
        
        # Log connection details
        peer = writer.get_extra_info('peername')
        logger.info(f"New connection from {peer}")
        
        try:
            # Loop to handle commands from this client
            while True:
                # Read a command from the client
                command = await self._read_command(conn)
                if not command:
                    logger.debug(f"Empty command or connection closed by client {peer}")
                    break
                
                # Update the last activity time
                conn.update_activity()
                
                # Handle the command and get a response
                response = await self.handle_command(conn, command, peer)
                
                # Send the response back to the client
                if response:
                    await self._send_response(conn, response)
                
        except asyncio.CancelledError:
            logger.info(f"Connection handler for {peer} cancelled")
        except asyncio.IncompleteReadError:
            logger.info(f"Connection closed by client {peer}")
        except ConnectionResetError:
            logger.info(f"Connection reset by client {peer}")
        except Exception as e:
            logger.error(f"Error handling connection from {peer}: {e}", exc_info=True)
        finally:
            # Clean up the connection
            await self._cleanup_connection(conn)
    
    async def _read_command(self, conn: TCPConnection) -> Optional[str]:
        """
        Read a command from the client.
        
        Args:
            conn: The TCP connection
            
        Returns:
            The command string, or None if the connection is closed
        """
        try:
            # Read a line from the client (terminated by \n)
            data = await conn.reader.readline()
            if not data:
                return None
            
            # Decode the data
            command = data.decode('latin1').strip()
            logger.debug(f"Received from {conn.peer}: {command}")
            
            return command
            
        except Exception as e:
            logger.error(f"Error reading command from {conn.peer}: {e}")
            return None
    
    async def _send_response(self, conn: TCPConnection, response: Union[str, bytes]) -> None:
        """
        Send a response to the client.
        
        Args:
            conn: The TCP connection
            response: The response to send (string or bytes)
        """
        try:
            # Convert string to bytes if necessary
            if isinstance(response, str):
                response_bytes = response.encode('latin1')
            else:
                response_bytes = response
            
            # Ensure response ends with \r\n for Delphi client compatibility
            if not response_bytes.endswith(b'\r\n'):
                response_bytes += b'\r\n'
            
            # Log the response (truncated if too long)
            if len(response_bytes) < 500:
                log_response = response_bytes
            else:
                log_response = response_bytes[:500] + b'... (truncated)'
            logger.debug(f"Sending to {conn.peer}: {log_response}")
            
            # Send the response
            conn.writer.write(response_bytes)
            await conn.writer.drain()
            
        except Exception as e:
            logger.error(f"Error sending response to {conn.peer}: {e}")
    
    async def _cleanup_connection(self, conn: TCPConnection):
        """
        Clean up a connection.
        
        Args:
            conn: The TCP connection to clean up
        """
        try:
            # Remove from connections dict if present
            if conn.client_id and conn.client_id in self.connections:
                self.connections.pop(conn.client_id)
                logger.debug(f"Removed connection for client {conn.client_id} from active connections")
            
            # Close the connection
            await conn.close()
            
        except Exception as e:
            logger.error(f"Error cleaning up connection: {e}")
    
    def check_duplicate_client_id(self, client_id: str, current_conn: TCPConnection) -> bool:
        """
        Check if a client ID is already in use by another connection.
        
        Args:
            client_id: The client ID to check
            current_conn: The current connection (to exclude from the check)
            
        Returns:
            True if the client ID is a duplicate, False otherwise
        """
        if client_id in self.connections:
            existing_conn = self.connections[client_id]
            
            # If it's the same connection, it's not a duplicate
            if existing_conn is current_conn:
                return False
            
            # Check if the existing connection is still active
            try:
                # Check if the socket is still connected
                if existing_conn.writer.is_closing():
                    logger.info(f"Existing connection for client {client_id} is closing, allowing new connection")
                    return False
                
                # Check if the connection is still active based on last activity
                idle_time = (datetime.now() - existing_conn.last_activity).total_seconds()
                if idle_time > CONNECTION_TIMEOUT:
                    logger.info(f"Existing connection for client {client_id} is inactive "
                                f"(idle for {idle_time:.1f} seconds), allowing new connection")
                    return False
                
                # It's a duplicate
                logger.warning(f"Duplicate client ID {client_id} detected. Existing connection from {existing_conn.peer}")
                return True
                
            except Exception as e:
                logger.error(f"Error checking existing connection for client {client_id}: {e}")
                # If we can't check, assume it's not active
                return False
        
        return False
    
    async def _handle_duplicate_connection(self, conn: TCPConnection, client_id: str):
        """
        Handle a duplicate client ID.
        
        Args:
            conn: The TCP connection with the duplicate ID
            client_id: The duplicate client ID
        """
        try:
            # Log the duplicate connection
            logger.warning(f"Client {client_id} already connected. Disconnecting new connection from {conn.peer}")
            
            # Send an error response and close the connection
            error_message = self.message_handler.format_error_response(
                f"Client ID {client_id} already connected. Please try again later."
            )
            await self._send_response(conn, error_message)
            
            # Close the connection
            await conn.close()
            
        except Exception as e:
            logger.error(f"Error handling duplicate connection: {e}")
    
    async def handle_command(self, conn: TCPConnection, command: str, peer: tuple) -> Union[str, bytes]:
        """
        Handle a command from a client.
        
        Args:
            conn: The TCP connection
            command: The command string
            peer: The client's address information
            
        Returns:
            The response to send back to the client
        """
        try:
            # Parse the command and parameters
            cmd_parts = command.strip().split()
            if not cmd_parts:
                logger.warning(f"Empty command from {peer}")
                return "ERROR Empty command"
            
            # Get the command name (first part)
            cmd = cmd_parts[0].upper()
            logger.info(f"Handling command: {cmd} from {peer}")
            
            # Check if command requires authentication (except INIT)
            if cmd != CMD_INIT and not conn.initialized:
                logger.warning(f"Command {cmd} received before initialization from {peer}")
                return self.message_handler.format_error_response("Client not initialized. Send INIT command first.")
            
            # Handle different commands
            if cmd == CMD_INIT:
                # Parse the client ID from the INIT command
                if len(cmd_parts) < 2 or not cmd_parts[1].startswith("ID="):
                    logger.warning(f"Invalid INIT command format from {peer}: {command}")
                    return self.message_handler.format_error_response("Invalid INIT command format. Use: INIT ID=x")
                
                # Extract the client ID
                try:
                    client_id = int(cmd_parts[1].split('=')[1])
                    logger.info(f"Client ID: {client_id}")
                except ValueError:
                    logger.warning(f"Invalid client ID in INIT command from {peer}: {command}")
                    return self.message_handler.format_error_response("Invalid client ID. Must be a number.")
                
                # Store client ID in connection
                conn.client_id = str(client_id)
                
                # Check for duplicate client ID
                if self.check_duplicate_client_id(conn.client_id, conn):
                    await self._handle_duplicate_connection(conn, conn.client_id)
                    return None  # Connection has been closed, no response needed
                
                # Add to active connections
                self.connections[conn.client_id] = conn
                
                # Generate keys for this client
                server_key, key_length, crypto_key = self.key_manager.get_key_for_client_id(client_id, conn.client_host)
                
                # Store key info on connection
                conn.server_key = server_key
                conn.key_length = key_length
                conn.set_crypto_key(crypto_key)
                
                # Test the encryption
                test_result = conn.test_encryption()
                logger.info(f"Encryption test result: {test_result}")
                
                # Mark as initialized
                conn.initialized = True
                
                # Format and return the INIT response
                return self.message_handler.format_init_response(server_key, key_length)
                
            elif cmd == CMD_PING:
                # Update last ping time
                conn.last_ping = datetime.now()
                return "PONG"
                
            elif cmd == CMD_INFO:
                return await self._handle_info_command(conn, cmd_parts)
                
            elif cmd == CMD_VERS:
                return await self._handle_version_command(conn, cmd_parts)
                
            elif cmd == CMD_ERRL:
                return await self._handle_error_command(conn, cmd_parts)
                
            elif cmd == CMD_DWNL:
                return await self._handle_download_command(conn, cmd_parts)
                
            else:
                logger.warning(f"Unknown command from {peer}: {cmd}")
                return self.message_handler.format_error_response(f"Unknown command: {cmd}")
                
        except Exception as e:
            logger.error(f"Error handling command from {peer}: {e}", exc_info=True)
            return self.message_handler.format_error_response(f"Internal server error: {str(e)}")
    
    async def _handle_info_command(self, conn: TCPConnection, cmd_parts: List[str]) -> str:
        """
        Handle the INFO command.
        
        Args:
            conn: The TCP connection
            cmd_parts: The command parts
            
        Returns:
            The response to send back to the client
        """
        try:
            logger.info(f"Handling INFO command from {conn.peer}")
            
            # Parse parameters if any
            params = {}
            for part in cmd_parts[1:]:
                if '=' in part:
                    key, value = part.split('=', 1)
                    params[key.upper()] = value
            
            # Check for DATA parameter
            if 'DATA' in params:
                # Try to decrypt the data
                encrypted_data = params['DATA']
                decrypted_data = conn.decrypt_data(encrypted_data)
                
                if not decrypted_data:
                    logger.warning(f"Failed to decrypt DATA in INFO command from {conn.peer}: {conn.last_error}")
                    return self.message_handler.format_error_response("Failed to decrypt data")
                
                # Log the decrypted data
                logger.debug(f"Decrypted DATA from INFO command: {decrypted_data}")
                
                # Parse the decrypted data parameter
                data_params = self.message_handler.parse_data_parameter(decrypted_data)
                logger.debug(f"Parsed DATA parameters: {data_params}")
            
            # Generate response data
            response_data = self.message_handler.generate_info_response(conn.client_id)
            
            # Format the response data with newlines
            formatted_data = self.message_handler.format_data_response(response_data, use_newlines=True)
            
            # Encrypt the response data
            encrypted_response = conn.encrypt_data(formatted_data)
            if not encrypted_response:
                logger.error(f"Failed to encrypt INFO response for {conn.peer}: {conn.last_error}")
                return self.message_handler.format_error_response("Failed to encrypt response")
            
            # Return the standard response with encrypted data
            return self.message_handler.format_standard_response(encrypted_response)
            
        except Exception as e:
            logger.error(f"Error handling INFO command from {conn.peer}: {e}", exc_info=True)
            return self.message_handler.format_error_response(f"Error processing INFO command: {str(e)}")
    
    async def _handle_version_command(self, conn: TCPConnection, cmd_parts: List[str]) -> str:
        """
        Handle the VERS command.
        
        Args:
            conn: The TCP connection
            cmd_parts: The command parts
            
        Returns:
            The response to send back to the client
        """
        try:
            logger.info(f"Handling VERS command from {conn.peer}")
            
            # Simple version response
            version_info = {
                "TT": "Test",
                "VER": "1.0.0",
                "BUILD": "100",
                "DATE": datetime.now().strftime("%Y-%m-%d")
            }
            
            # Format the response data with newlines
            formatted_data = self.message_handler.format_data_response(version_info, use_newlines=True)
            
            # Encrypt the response data
            encrypted_response = conn.encrypt_data(formatted_data)
            if not encrypted_response:
                logger.error(f"Failed to encrypt VERS response for {conn.peer}: {conn.last_error}")
                return self.message_handler.format_error_response("Failed to encrypt response")
            
            # Return the standard response with encrypted data
            return self.message_handler.format_standard_response(encrypted_response)
            
        except Exception as e:
            logger.error(f"Error handling VERS command from {conn.peer}: {e}", exc_info=True)
            return self.message_handler.format_error_response(f"Error processing VERS command: {str(e)}")
    
    async def _handle_error_command(self, conn: TCPConnection, cmd_parts: List[str]) -> str:
        """
        Handle the ERRL command.
        
        Args:
            conn: The TCP connection
            cmd_parts: The command parts
            
        Returns:
            The response to send back to the client
        """
        try:
            logger.info(f"Handling ERRL command from {conn.peer}")
            
            # Parse parameters
            params = {}
            for part in cmd_parts[1:]:
                if '=' in part:
                    key, value = part.split('=', 1)
                    params[key.upper()] = value
            
            # Check for DATA parameter
            if 'DATA' in params:
                # Try to decrypt the data
                encrypted_data = params['DATA']
                decrypted_data = conn.decrypt_data(encrypted_data)
                
                if not decrypted_data:
                    logger.warning(f"Failed to decrypt DATA in ERRL command from {conn.peer}: {conn.last_error}")
                    return self.message_handler.format_error_response("Failed to decrypt data")
                
                # Log the error from the client
                logger.info(f"Client error report from {conn.peer}: {decrypted_data}")
            
            # Acknowledge the error
            return "200 OK"
            
        except Exception as e:
            logger.error(f"Error handling ERRL command from {conn.peer}: {e}", exc_info=True)
            return self.message_handler.format_error_response(f"Error processing ERRL command: {str(e)}")
    
    async def _handle_download_command(self, conn: TCPConnection, cmd_parts: List[str]) -> str:
        """
        Handle the DWNL command.
        
        Args:
            conn: The TCP connection
            cmd_parts: The command parts
            
        Returns:
            The response to send back to the client
        """
        try:
            logger.info(f"Handling DWNL command from {conn.peer}")
            
            # Parse parameters
            params = {}
            for part in cmd_parts[1:]:
                if '=' in part:
                    key, value = part.split('=', 1)
                    params[key.upper()] = value
            
            # This is a placeholder implementation - in a real server, you would handle
            # file downloads here
            download_info = {
                "TT": "Test",
                "STATUS": "NOFILES",
                "MESSAGE": "No files available for download"
            }
            
            # Format the response data with newlines
            formatted_data = self.message_handler.format_data_response(download_info, use_newlines=True)
            
            # Encrypt the response data
            encrypted_response = conn.encrypt_data(formatted_data)
            if not encrypted_response:
                logger.error(f"Failed to encrypt DWNL response for {conn.peer}: {conn.last_error}")
                return self.message_handler.format_error_response("Failed to encrypt response")
            
            # Return the standard response with encrypted data
            return self.message_handler.format_standard_response(encrypted_response)
            
        except Exception as e:
            logger.error(f"Error handling DWNL command from {conn.peer}: {e}", exc_info=True)
            return self.message_handler.format_error_response(f"Error processing DWNL command: {str(e)}") 
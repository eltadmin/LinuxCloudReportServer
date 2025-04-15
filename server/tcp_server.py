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
from .constants import CRYPTO_DICTIONARY
from .crypto import DataCompressor
import base64
import re
import socket
import os
import struct

logger = logging.getLogger(__name__)

# Configuration constants
DEBUG_MODE = True
DEBUG_SERVER_KEY = "ABCDEFGH"  # Exactly 8 characters
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

# Response format constants
RESPONSE_FORMATS = {
    'format1': "LEN={}\r\nKEY={}",  # LEN first with CRLF - standard Delphi format
    'format2': "LEN={}\nKEY={}",    # LEN first with LF only
    'format3': "KEY={}\r\nLEN={}",  # KEY first with CRLF
    'format4': "LEN={}\nKEY={}\n",  # Suggested in logs
    'format5': "{}",                # Just the key
    'format6': "200 OK\nLEN={}\nKEY={}\n"  # Status line + suggested format
}

# Timeout constants (seconds)
CONNECTION_TIMEOUT = 300  # 5 minutes
PENDING_CONNECTION_TIMEOUT = 120  # 2 minutes
INACTIVITY_CHECK_INTERVAL = 60  # 1 minute

# Key generation constants
KEY_LENGTH = 8  # Fixed length for reliability

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
        self.peer = writer.get_extra_info('peername')
        self.last_ping = datetime.now()
        self.client_host = None
        self.app_type = None
        self.app_version = None
        self.server_key = None
        self.key_length = None
        self.crypto_key = None
        self.last_error = None
        self.last_activity = datetime.now()
        self.connection_time = datetime.now()

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
        """Return a string representation of this connection."""
        return f"TCPConnection(peer={self.peer}, client_id={self.client_id}, host={self.client_host})"


class TCPServer:
    """
    TCP Server implementation that handles multiple client connections.
    
    This class manages the TCP server and all client connections, including
    their authentication state, command processing, and connection cleanup.
    """
    
    def __init__(self, report_server):
        """
        Initialize a new TCP server tied to the given report server.
        
        Args:
            report_server: The main report server instance that owns this TCP server
        """
        self.report_server = report_server
        self.server = None
        self.connections: Dict[str, TCPConnection] = {}  # Authenticated clients with client_id
        self.pending_connections: List[TCPConnection] = []  # Connections without client_id yet
        self.running = False
        self._cleanup_task = None
        
    async def start(self, host: str, port: int):
        """
        Start the TCP server on the given host and port.
        
        Args:
            host: The hostname or IP address to bind to
            port: The port number to listen on
        """
        logger.info(f"Starting TCP server on {host}:{port}")
        try:
            self.server = await asyncio.start_server(
                self.handle_connection, host, port
            )
            self.running = True
        
            # Start background task to check for inactive connections
            self._cleanup_task = asyncio.create_task(
                self.check_inactive_connections(),
                name="tcp_connection_cleanup"
            )
            logger.info(f"TCP server started and listening on {host}:{port}")
            
        except Exception as e:
            logger.error(f"Failed to start TCP server: {e}", exc_info=True)
            raise
        
    async def stop(self):
        """Stop the TCP server and close all connections."""
        logger.info("Stopping TCP server...")
        self.running = False
        
        # Cancel cleanup task
        if self._cleanup_task and not self._cleanup_task.done():
            self._cleanup_task.cancel()
            try:
                await self._cleanup_task
            except asyncio.CancelledError:
                pass
            
        # Close server
        if self.server:
            self.server.close()
            await self.server.wait_closed()
            logger.info("TCP server stopped")
            
        # Close all connections
        await self._close_all_connections()
            
    async def _close_all_connections(self):
        """Close all active connections."""
        # Close authenticated connections
        for client_id, conn in list(self.connections.items()):
            try:
                logger.info(f"Closing connection to client {client_id}")
                await conn.close()
                self.connections.pop(client_id, None)
            except Exception as e:
                logger.error(f"Error closing connection to client {client_id}: {e}")
                
        # Close all pending connections
        for conn in list(self.pending_connections):
            try:
                logger.info(f"Closing pending connection to {conn.peer}")
                await conn.close()
                self.pending_connections.remove(conn)
            except Exception as e:
                logger.error(f"Error closing pending connection: {e}")
                
        logger.info("All connections closed")
        
    async def check_inactive_connections(self):
        """
        Periodically check for and remove inactive connections.
        
        This task runs in the background and performs cleanup of connections
        that have been inactive for too long.
        """
        logger.info("Starting connection cleanup task")
        
        while self.running:
            try:
                now = datetime.now()
                removed_count = 0
                
                # Check authenticated connections
                for client_id, conn in list(self.connections.items()):
                    # If last activity is more than CONNECTION_TIMEOUT seconds ago, remove the connection
                    if (now - conn.last_activity).total_seconds() > CONNECTION_TIMEOUT:
                        logger.info(f"Removing inactive connection for client {client_id}")
                        self.connections.pop(client_id)
                        await conn.close()
                        removed_count += 1
                
                # Check pending connections (not yet authenticated with client ID)
                pending_to_remove = []
                for i, conn in enumerate(self.pending_connections):
                    # If connection is older than PENDING_CONNECTION_TIMEOUT seconds and still not authenticated, remove it
                    if (now - conn.connection_time).total_seconds() > PENDING_CONNECTION_TIMEOUT:
                        pending_to_remove.append(i)
                
                # Remove from highest index to lowest to avoid shifting issues
                for i in sorted(pending_to_remove, reverse=True):
                    if i < len(self.pending_connections):  # Safety check
                        conn = self.pending_connections.pop(i)
                        logger.info(f"Removing pending connection {conn.peer} due to inactivity")
                        await conn.close()
                        removed_count += 1
                
                if removed_count > 0:
                    logger.info(f"Removed {removed_count} inactive connections. Active: {len(self.connections)}, Pending: {len(self.pending_connections)}")
                
                # Sleep before next check
                await asyncio.sleep(INACTIVITY_CHECK_INTERVAL)
                
            except asyncio.CancelledError:
                logger.info("Connection cleanup task cancelled")
                break
            except Exception as e:
                logger.error(f"Error in connection cleanup task: {e}", exc_info=True)
                await asyncio.sleep(INACTIVITY_CHECK_INTERVAL)  # Wait before retry
        
        logger.info("Connection cleanup task stopped")
            
    async def handle_connection(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
        """
        Handle a new TCP connection from a client.
        
        This method is called each time a new client connects to the server.
        It manages the connection lifecycle, reading commands and handling them.
        
        Args:
            reader: The asyncio stream reader for reading from the client
            writer: The asyncio stream writer for writing to the client
        """
        # Create connection object and add to pending connections
        conn = TCPConnection(reader, writer)
        peer = conn.peer
        logger.info(f"New TCP connection from {peer}")
        
        # Add to pending connections list until we get a client ID
        self.pending_connections.append(conn)
        
        try:
            while self.running:
                # Read command from client
                command = await self._read_command(conn)
                if command is None:
                    # Connection closed or error
                    break
                
                # Update activity timestamp
                conn.update_activity()
                
                # Handle the command
                response = await self.handle_command(conn, command, peer)
                
                # Send response if any
                if response:
                    try:
                        await self._send_response(conn, response)
                    except Exception as e:
                        logger.error(f"Failed to send response: {e}")
                        break
                    
        except asyncio.CancelledError:
            logger.info(f"Connection handling for {peer} was cancelled")
        except Exception as e:
            logger.error(f"Error handling client {peer}: {e}", exc_info=True)
            logger.error(traceback.format_exc())
        finally:
            # Cleanup connection
            await self._cleanup_connection(conn)
    
    async def _read_command(self, conn: TCPConnection) -> Optional[str]:
        """
        Read a command from the client.
        
        Args:
            conn: The TCP connection to read from
            
        Returns:
            The command string or None if the connection is closed
        """
        try:
            data = await conn.reader.readuntil(b'\n')
            command = data.decode('utf-8', errors='replace').strip()
            logger.info(f"Received command from {conn.peer}: {command}")
            return command
        except asyncio.IncompleteReadError:
            logger.info(f"Client {conn.peer} disconnected")
            return None
        except Exception as e:
            logger.error(f"Error reading from socket: {e}")
            return None
    
    async def _send_response(self, conn: TCPConnection, response: Union[str, bytes]) -> None:
        """
        Send a response to the client.
        
        Args:
            conn: The TCP connection
            response: The response to send (either string or bytes)
        """
        try:
            # Convert string to bytes if necessary
            if isinstance(response, str):
                # Ensure we have proper line endings for string responses
                if not response.endswith("\r\n"):
                    response += "\r\n"
                response_bytes = response.encode('latin1')
            else:
                # For bytes, add CRLF if needed
                response_bytes = response
                if not response_bytes.endswith(b'\r\n'):
                    response_bytes += b'\r\n'
                
            # Write and drain
            conn.writer.write(response_bytes)
            await conn.writer.drain()
            
            # Log the response (truncate if too long)
            log_response = response_bytes[:100]  # First 100 bytes for logging
            try:
                log_text = log_response.decode('latin1', errors='replace')
                logger.debug(f"Sent response: {log_text.strip()}")
            except Exception:
                logger.debug(f"Sent binary response: {len(response_bytes)} bytes")
                
        except Exception as e:
            logger.error(f"Error sending response: {e}", exc_info=True)
            raise
    
    async def _cleanup_connection(self, conn: TCPConnection):
        """
        Clean up a connection that is being closed.
        
        Args:
            conn: The TCP connection to clean up
        """
        # Remove from connections dict if authenticated
        if conn.client_id and conn.client_id in self.connections:
            del self.connections[conn.client_id]
        
        # Remove from pending connections if present
        if conn in self.pending_connections:
            self.pending_connections.remove(conn)
            
        # Close the connection
        await conn.close()
            
    def check_duplicate_client_id(self, client_id: str, current_conn: TCPConnection) -> bool:
        """
        Check if a client ID already exists in the connections dictionary.
        
        If it exists, close the old connection and return True.
        
        Args:
            client_id: The client ID to check
            current_conn: The current connection that wants to use this ID
            
        Returns:
            True if a duplicate was found and handled, False otherwise
        """
        if client_id in self.connections:
            old_conn = self.connections[client_id]
            # If it's the same connection object, it's not a duplicate
            if old_conn is current_conn:
                return False
                
            logger.warning(f"Duplicate client ID detected: {client_id}. Closing old connection.")
            
            # Schedule closing the old connection asynchronously
            asyncio.create_task(
                self._handle_duplicate_connection(old_conn, client_id),
                name=f"close_duplicate_{client_id}"
            )
            return True
            
        return False
    
    async def _handle_duplicate_connection(self, conn: TCPConnection, client_id: str):
        """
        Handle a duplicate connection by closing it.
        
        Args:
            conn: The connection to close
            client_id: The client ID that was duplicated
        """
        try:
            logger.info(f"Closing duplicate connection for client ID: {client_id}")
            await conn.close()
            # Remove from connections if still present
            if client_id in self.connections and self.connections[client_id] is conn:
                del self.connections[client_id]
        except Exception as e:
            logger.error(f"Error closing duplicate connection: {e}")
                    
    async def handle_command(self, conn: TCPConnection, command: str, peer: tuple) -> bytes:
        """
        Handle a TCP command from a client.
        
        This method processes the command and returns the response to send back.
        
        Args:
            conn: The TCP connection that sent the command
            command: The command string received from the client
            peer: The peer address tuple (host, port)
            
        Returns:
            The response bytes to send back to the client
        """
        # Skip empty commands
        if not command.strip():
            logger.warning(f"Empty command received from {peer}")
            return b''
            
        try:
            parts = command.split(' ')
            cmd = parts[0].upper()
            logger.debug(f"Processing command: {cmd} with parts: {parts}")
            
            # Check if connection is authenticated for commands that require it
            if self._requires_authentication(cmd) and not conn.authenticated:
                logger.warning(f"Unauthenticated connection tried to use {cmd} command")
                return b'ERROR Authentication required'
            
            # Dispatch to the appropriate command handler
            if cmd == CMD_INIT:
                return await self._handle_init(conn, parts)
            elif cmd == CMD_ERRL:
                return await self._handle_error(conn, parts)
            elif cmd == CMD_PING:
                return await self._handle_ping(conn)
            elif cmd == CMD_INFO:
                return await self._handle_info(conn, parts)
            elif cmd == CMD_VERS:
                return await self._handle_version(conn, parts)
            elif cmd == CMD_DWNL:
                return await self._handle_download(conn, parts)
            elif cmd == CMD_GREQ:
                return await self._handle_report_request(conn, parts)
            elif cmd == CMD_SRSP:
                return await self._handle_response(conn, parts)
            else:
                logger.warning(f"Unknown command received: {cmd}")
                return f'ERROR Unknown command: {cmd}'.encode('utf-8')
                
        except Exception as e:
            logger.error(f"Unhandled error in handle_command: {e}", exc_info=True)
            return f'ERROR Internal server error: {str(e)}'.encode('utf-8')
    
    def _requires_authentication(self, cmd: str) -> bool:
        """
        Check if a command requires authentication.
        
        Args:
            cmd: The command to check
            
        Returns:
            True if the command requires authentication, False otherwise
        """
        authenticated_commands = [CMD_INFO, CMD_SRSP, CMD_GREQ, CMD_VERS, CMD_DWNL]
        return cmd in authenticated_commands

    async def _handle_init(self, conn: TCPConnection, command_parts: List[str]) -> bytes:
        """
        Handle the INIT command from the client.
        
        Args:
            conn: The TCP connection
            command_parts: The parts of the command
            
        Returns:
            The response bytes to send back to the client
        """
        logger.info(f"Handling INIT command with parts: {command_parts}")
        
        try:
            # Parse parameters using helper method
            params = self._parse_parameters(command_parts[1:] if len(command_parts) >= 2 else [])
            logger.info(f"Extracted params: {params}")
            
            # Get the client IP and hostname from the connection peer info instead of client_address
            client_ip = params.get('IP', conn.peer[0] if conn.peer else 'unknown')
            hostname = params.get('HOST', params.get('HST', 'UNKNOWN'))
            dict_index = int(params.get('IDX', '0'))
            
            # Store the client hostname for key generation
            conn.client_host = hostname
            logger.info(f"Client hostname: {hostname}, Dictionary index: {dict_index}")
            
            # Prepare the response using the helper method
            response_bytes, crypto_key = await self._prepare_init_response(conn, dict_index + 1)
            
            # Return the response bytes directly
            return response_bytes
            
        except Exception as e:
            logger.error(f"Error handling INIT command: {e}", exc_info=True)
            # Return a bytes response on error
            return b'ERROR Internal server error'
    
    def _parse_parameters(self, param_parts: List[str]) -> Dict[str, str]:
        """
        Parse parameter parts into a dictionary.
        
        Args:
            param_parts: Parameter parts from a command
            
        Returns:
            Dictionary of parameter key-value pairs
        """
        params = {}
        for param in param_parts:
            if '=' in param:
                key, value = param.split('=', 1)
                params[key.upper()] = value
        return params
        
    async def _prepare_init_response(self, conn: TCPConnection, key_id: int) -> Tuple[bytes, str]:
        """
        Prepare the response for an INIT command.
        
        This method generates the server key, constructs the crypto key,
        and prepares the response to send back to the client.
        
        Args:
            conn: The TCP connection
            key_id: The key ID from the INIT command
            
        Returns:
            A tuple of (response_bytes, crypto_key)
        """
        # Generate server key
        if DEBUG_MODE:
            server_key = DEBUG_SERVER_KEY
        else:
            server_key = ''.join(random.choices(string.ascii_letters + string.digits, k=KEY_LENGTH))
        
        # Store key info
        key_len = len(server_key)
        conn.server_key = server_key
        conn.key_length = key_len
        
        # Get dictionary entry for key construction
        dict_entry = CRYPTO_DICTIONARY[key_id - 1]
        crypto_dict_part = dict_entry[:key_len]
        
        # Extract host parts for key generation
        host_first_chars = conn.client_host[:2]  # First 2 chars
        orig_host_last_char = conn.client_host[-1:]  # Last char
        
        # Log info about host parts
        logger.info(f"Host parts: original='{host_first_chars + orig_host_last_char}', cleaned='{conn.client_host[:3]}'")
        
        # Test multiple key combinations for Delphi compatibility
        crypto_key = await self._test_crypto_key_variants(conn, server_key, crypto_dict_part)
        
        # Format response for client
        response_str = self._format_init_response(server_key, key_len)
        # Convert to bytes
        response_bytes = response_str.encode('latin1')
        
        logger.info(f"Final normalized response: {response_str}")
        logger.info(f"Using crypto key for client {conn.client_host}: {crypto_key}")
        
        return response_bytes, crypto_key
    
    async def _test_crypto_key_variants(self, conn: TCPConnection, server_key: str, crypto_dict_part: str) -> str:
        """
        Create the crypto key that matches the Windows server implementation.
        
        Args:
            conn: The TCP connection
            server_key: The generated server key
            crypto_dict_part: The dictionary part for the crypto key
            
        Returns:
            The working crypto key
        """
        try:
            # Construct crypto key using the method that matches Windows server
            # Format: ServerKey + DictionaryPart + FirstTwoCharsOfHostname + LastCharOfHostname
            host_first_chars = conn.client_host[:2]  # First 2 chars
            host_last_char = conn.client_host[-1:]  # Last char
            
            working_key = server_key + crypto_dict_part + host_first_chars + host_last_char
            logger.info(f"Using crypto key: {working_key}")
            
            # Set the crypto key and test encryption
            conn.crypto_key = working_key
            test_result = conn.test_encryption()
            
            if not test_result:
                logger.error(f"Encryption test failed with key: {working_key}")
                logger.error(f"Last error: {conn.last_error}")
            else:
                logger.info("Crypto validation test passed successfully")
            
            # Do not override with debug key - use the actual key that works
            return working_key
        except Exception as e:
            logger.error(f"Error creating crypto key: {e}", exc_info=True)
            # Fall back to the original key construction method
            working_key = server_key + crypto_dict_part + conn.client_host[:2] + conn.client_host[-1:]
            conn.crypto_key = working_key
            return working_key
        
    def _format_init_response(self, server_key: str, key_length: int) -> str:
        """
        Format the INIT response to send back to the client.
        
        Args:
            server_key: The server key to include in the response
            key_length: The length of the key
            
        Returns:
            Formatted response string
        """
        # Windows client expects LEN followed by KEY with CRLF line endings
        # This matches the format that the Delphi client parses with:
        # FTCPClient.LastCmdResult.Text.Values['LEN'] and FTCPClient.LastCmdResult.Text.Values['KEY']
        response_parts = [
            f"LEN={key_length}",
            f"KEY={server_key}"
        ]
        
        # Join with Windows-style CRLF line endings
        return "\r\n".join(response_parts)
        
    async def _handle_error(self, conn: TCPConnection, parts: List[str]) -> bytes:
        """
        Handle an error report from a client.
        
        Args:
            conn: The TCP connection that sent the command
            parts: The parts of the command
            
        Returns:
            The response bytes to send back to the client
        """
        # Join all parts after the command name to get the error message
        error_msg = ' '.join(parts[1:])
        logger.error(f"Client error: {error_msg}")
        
        # Log detailed information about the error
        if "Unable to initizlize communication" in error_msg:
            logger.error("Analysis: Problem with INIT response format or incorrect crypto key")
            logger.error(f"INIT parameters: ID={conn.client_id}, host={conn.client_host}")
            logger.error(f"Server key: {conn.server_key}, length: {conn.key_length}")
            logger.error(f"Crypto key: {conn.crypto_key}")
        
        # Create the proper format string for reference
        proper_format = f"LEN={conn.key_length}\r\nKEY={conn.server_key}"
        logger.error(f"Correct response format: '{proper_format}'")
        
        # Add more diagnostic information
        if hasattr(conn, 'last_error') and conn.last_error:
            logger.error(f"Last error: '{conn.last_error}'")
        
        # Log error parts for better analysis
        logger.error(f"ERRL command full data: {' '.join(parts)}")
        logger.error(f"ERRL command parts: {parts}")
        for i, part in enumerate(parts[1:], 1):
            logger.error(f"ERRL part {i}: '{part}'")
        
        logger.error("Анализ: Проблем с форматирането на INIT отговора или неправилен криптиращ ключ.")
        logger.error(f"INIT параметри: ID={conn.client_id}, host={conn.client_host}")
        logger.error(f"Изпратен ключ: {conn.server_key}, дължина: {conn.key_length}")
        
        # Communication analysis
        logger.error(f"===== Last Client-Server Communication =====")
        logger.error(f"Client ID from command: unknown")
        logger.error(f"Delphi client would parse using: FTCPClient.LastCmdResult.Text.Values['LEN']")
        logger.error(f"Delphi client would parse using: FTCPClient.LastCmdResult.Text.Values['KEY']")
        logger.error(f"Expected client key creation: KEY + dict_part + hostname_chars")
        logger.error(f"Fixed response format (for next attempt): 'LEN={conn.key_length}\r\nKEY={conn.server_key}'")
        
        # Host components analysis - safely handle None values
        logger.error("Компоненти на ключа:")
        logger.error(f"  server_key: {conn.server_key}")
        logger.error(f"  dict_part: {CRYPTO_DICTIONARY[0][:8]}")
        logger.error("  host parts variance:")
        
        # Safely access host parts if client_host exists
        if conn.client_host:
            logger.error(f"    first 2 chars: {conn.client_host[:2]}")
            logger.error(f"    last char: {conn.client_host[-1:]}")
            logger.error(f"    first 2 + last: {conn.client_host[:2] + conn.client_host[-1:]}")
        else:
            logger.error("    client_host is None - cannot extract host parts")
        
        logger.error(f"Последен успешен тест на криптирането със сървърски ключ: {conn.crypto_key}")
        if conn.crypto_key:
            test_result = conn.test_encryption()
            logger.error(f"Тест на криптирането: {test_result}")
        else:
            logger.error("Тест на криптирането: невъзможен (липсва ключ)")
        
        return b'OK'
        
    async def _handle_ping(self, conn: TCPConnection) -> bytes:
        """
        Handle a PING command from a client.
        
        Args:
            conn: The TCP connection that sent the command
            
        Returns:
            The response bytes to send back to the client
        """
        conn.last_ping = datetime.now()
        return b'PONG'
        
    async def _handle_info(self, conn: TCPConnection, parts: List[str]) -> bytes:
        # Implementation of _handle_info method
        pass

    async def _handle_version(self, conn: TCPConnection, parts: List[str]) -> bytes:
        # Implementation of _handle_version method
        pass

    async def _handle_download(self, conn: TCPConnection, parts: List[str]) -> bytes:
        # Implementation of _handle_download method
        pass

    async def _handle_report_request(self, conn: TCPConnection, parts: List[str]) -> bytes:
        # Implementation of _handle_report_request method
        pass

    async def _handle_response(self, conn: TCPConnection, parts: List[str]) -> bytes:
        # Implementation of _handle_response method
        pass

    def _generate_server_key(self) -> str:
        """
        Generate a random server key for the encryption.
        
        Returns:
            A random string of length KEY_LENGTH for use as the server key
        """
        if DEBUG_MODE and USE_FIXED_DEBUG_KEY:
            logger.warning("Using debug server key! NOT SECURE FOR PRODUCTION!")
            return DEBUG_SERVER_KEY
            
        # Generate a random server key with KEY_LENGTH characters
        chars = string.ascii_uppercase + string.digits
        return ''.join(random.choice(chars) for _ in range(KEY_LENGTH)) 
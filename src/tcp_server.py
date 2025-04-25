"""
TCP server implementation for Cloud Report Server
"""

import re
import socket
import sys
import threading
import time
import traceback
from typing import Dict, List, Optional, Tuple, Any

from constants import (
    DROP_DEVICE_WITHOUT_ACTIVITY_SEC,
    DROP_DEVICE_WITHOUT_SERIAL_TIME_SEC,
    LINE_SEPARATOR,
    RESPONSE_OK,
    TCP_ERR_COMMAND_UNKNOWN,
    TCP_ERR_DUPLICATE_CLIENT_ID,
)
from connection import TCPConnection, TCPCommandHandler
from logger import Logger

class TcpServer:
    """TCP server implementation"""
    
    def __init__(self, host: str, port: int, log_path: str, auth_server_url: str):
        """
        Initialize the TCP server
        
        Args:
            host: Host to bind to
            port: Port to bind to
            log_path: Path to log files
            auth_server_url: URL of the authentication server
        """
        self.host = host
        self.port = port
        self.log_path = log_path
        self.auth_server_url = auth_server_url
        self.logger = Logger(log_path)
        
        # Active connections
        self.connections: Dict[str, TCPConnection] = {}
        self.connections_lock = threading.Lock()
        
        # Server socket
        self.server_socket = None
        
        # Server thread
        self.server_thread = None
        self.cleanup_thread = None
        self.running = False
    
    def start(self) -> None:
        """Start the TCP server"""
        if self.running:
            return
        
        self.running = True
        
        try:
            # Create server socket
            self.server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            self.server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            
            try:
                # Bind and listen
                self.logger.log(f"Binding TCP server to {self.host}:{self.port}")
                self.server_socket.bind((self.host, self.port))
                self.server_socket.listen(5)
                
                # Start server thread
                self.server_thread = threading.Thread(target=self._accept_connections)
                self.server_thread.daemon = True
                self.server_thread.start()
                
                # Start cleanup thread
                self.cleanup_thread = threading.Thread(target=self._cleanup_connections)
                self.cleanup_thread.daemon = True
                self.cleanup_thread.start()
                
                self.logger.log(f"TCP server started on {self.host}:{self.port}")
                
            except socket.error as e:
                self.running = False
                error_msg = f"Failed to bind TCP server to {self.host}:{self.port}: {e}"
                self.logger.log(error_msg)
                print(error_msg, file=sys.stderr)
                print(traceback.format_exc(), file=sys.stderr)
                
                # Check if another process is using the port
                try:
                    test_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
                    result = test_socket.connect_ex((self.host, self.port))
                    test_socket.close()
                    
                    if result == 0:
                        msg = f"Port {self.port} is already in use by another application"
                        self.logger.log(msg)
                        print(msg, file=sys.stderr)
                except Exception:
                    pass
                
                raise
                
        except Exception as e:
            self.running = False
            error_msg = f"Failed to start TCP server: {e}"
            self.logger.log(error_msg)
            print(error_msg, file=sys.stderr)
            print(traceback.format_exc(), file=sys.stderr)
            raise
    
    def stop(self) -> None:
        """Stop the TCP server"""
        if not self.running:
            return
        
        self.running = False
        
        try:
            # Close server socket
            if self.server_socket:
                self.server_socket.close()
                self.server_socket = None
            
            # Close all connections
            with self.connections_lock:
                for connection in list(self.connections.values()):
                    try:
                        connection.client_socket.close()
                    except Exception:
                        pass
                
                self.connections.clear()
            
            self.logger.log("TCP server stopped")
        except Exception as e:
            error_msg = f"Error stopping TCP server: {e}"
            self.logger.log(error_msg)
            print(error_msg, file=sys.stderr)
            print(traceback.format_exc(), file=sys.stderr)
    
    def _accept_connections(self) -> None:
        """Accept client connections"""
        while self.running:
            try:
                # Accept connection
                client_socket, address = self.server_socket.accept()
                client_socket.settimeout(60)  # Set timeout to 60 seconds
                
                # Log new connection
                self.logger.log(f"New client connection from {address[0]}:{address[1]}")
                
                # Handle connection in a new thread
                client_thread = threading.Thread(
                    target=self._handle_client,
                    args=(client_socket, address),
                    daemon=True
                )
                client_thread.start()
                
            except socket.timeout:
                # Socket timeout, just continue
                continue
                
            except Exception as e:
                if self.running:
                    error_msg = f"Error accepting client connection: {e}"
                    self.logger.log(error_msg)
                    print(error_msg, file=sys.stderr)
                    print(traceback.format_exc(), file=sys.stderr)
                    time.sleep(1)
    
    def _handle_client(self, client_socket: socket.socket, address: Tuple[str, int]) -> None:
        """
        Handle a client connection
        
        Args:
            client_socket: Client socket
            address: Client address (ip, port)
        """
        # Create connection object
        connection = None
        
        try:
            # Create connection object
            connection = TCPConnection(client_socket, address, self.log_path)
            
            # Create command handler
            handler = TCPCommandHandler(connection, self.auth_server_url)
            
            # Loop until connection is closed
            buffer = ""
            
            while not connection.must_disconnect and self.running:
                try:
                    # Receive data
                    data = client_socket.recv(4096)
                    
                    # If no data, client disconnected
                    if not data:
                        break
                    
                    # Add to buffer
                    buffer += data.decode('utf-8')
                    
                    # Process complete commands
                    while LINE_SEPARATOR in buffer:
                        # Split command
                        command, buffer = buffer.split(LINE_SEPARATOR, 1)
                        
                        # Process command
                        response = self._process_command(command, handler)
                        
                        # Send response
                        client_socket.sendall(f"{response}{LINE_SEPARATOR}".encode('utf-8'))
                
                except socket.timeout:
                    # Socket timeout, just continue
                    continue
                
                except Exception as e:
                    # Log error
                    error_msg = f"Error processing client data: {e}"
                    self.logger.log(error_msg)
                    print(error_msg, file=sys.stderr)
                    print(traceback.format_exc(), file=sys.stderr)
                    break
            
        except Exception as e:
            # Log error
            error_msg = f"Error handling client {address[0]}:{address[1]}: {e}"
            self.logger.log(error_msg)
            print(error_msg, file=sys.stderr)
            print(traceback.format_exc(), file=sys.stderr)
            
        finally:
            # Clean up connection
            try:
                client_socket.close()
            except Exception:
                pass
            
            # Remove from connections list if it was added
            if connection and connection.client_id:
                with self.connections_lock:
                    if connection.client_id in self.connections:
                        del self.connections[connection.client_id]
            
            # Log disconnection
            self.logger.log(f"Client disconnected from {address[0]}:{address[1]}")
    
    def _process_command(self, command: str, handler: TCPCommandHandler) -> str:
        """
        Process a command from a client
        
        Args:
            command: Command string
            handler: Command handler
            
        Returns:
            Response string
        """
        try:
            print(f"Received command: {command}")
            
            # Parse command and parameters
            parts = command.split()
            
            if not parts:
                print("Empty command received")
                return f"{TCP_ERR_COMMAND_UNKNOWN} Empty command"
            
            cmd = parts[0].upper()
            print(f"Command type: {cmd}")
            
            # Parse parameters (format: key=value)
            params = {}
            for part in parts[1:]:
                if "=" in part:
                    key, value = part.split("=", 1)
                    params[key] = value
            
            print(f"Command parameters: {params}")
            
            # Handle client identification
            connection = handler.connection
            
            # If client ID is set, add to connections list
            if connection.client_id and connection.client_id not in self.connections:
                print(f"New client ID: {connection.client_id}")
                with self.connections_lock:
                    # Check for duplicate client ID
                    if connection.client_id in self.connections:
                        # Another connection with the same ID exists
                        # Force disconnect both connections
                        other_conn = self.connections[connection.client_id]
                        other_conn.must_disconnect = True
                        connection.must_disconnect = True
                        
                        error_msg = f"Duplicate client ID: {connection.client_id}"
                        print(f"ERROR: {error_msg}")
                        self.logger.log(error_msg)
                        return f"{TCP_ERR_DUPLICATE_CLIENT_ID} {error_msg}"
                    
                    # Add to connections list
                    self.connections[connection.client_id] = connection
                    print(f"Added client ID {connection.client_id} to connections list")
                    self.logger.log(f"New client connected with ID: {connection.client_id}")
            
            # Handle command
            print(f"Executing command: {cmd}")
            response = handler.handle_command(command, params)
            print(f"Command response: {response[:100]}" + ("..." if len(response) > 100 else ""))
            
            return response
            
        except Exception as e:
            # Log error
            error_msg = f"Error handling command: {e}"
            self.logger.log(error_msg)
            print(error_msg, file=sys.stderr)
            print(traceback.format_exc(), file=sys.stderr)
            
            # Return error
            return f"{TCP_ERR_COMMAND_UNKNOWN} {str(e)}"
    
    def _cleanup_connections(self) -> None:
        """Clean up inactive connections"""
        while self.running:
            try:
                # Sleep for a while
                time.sleep(30)
                
                # Check all connections
                with self.connections_lock:
                    for client_id, connection in list(self.connections.items()):
                        # Check if connection is inactive
                        if connection.idle_time_sec > DROP_DEVICE_WITHOUT_ACTIVITY_SEC:
                            # Disconnect client
                            connection.must_disconnect = True
                            self.logger.log(f"Disconnecting inactive client: {client_id}")
                        
                        # Check if client ID is set
                        if not connection.client_id and connection.connected_time_sec > DROP_DEVICE_WITHOUT_SERIAL_TIME_SEC:
                            # Disconnect client
                            connection.must_disconnect = True
                            self.logger.log(f"Disconnecting unauthenticated client from {connection.connection_info.remote_ip}")
            
            except Exception as e:
                error_msg = f"Error cleaning up connections: {e}"
                self.logger.log(error_msg)
                print(error_msg, file=sys.stderr)
                print(traceback.format_exc(), file=sys.stderr)
    
    def get_client(self, client_id: str) -> Optional[TCPConnection]:
        """
        Get a client by ID
        
        Args:
            client_id: Client ID
            
        Returns:
            Client connection if found, None otherwise
        """
        with self.connections_lock:
            return self.connections.get(client_id)
    
    def get_client_list(self) -> List[Dict[str, str]]:
        """
        Get a list of connected clients
        
        Returns:
            List of client information
        """
        result = []
        
        with self.connections_lock:
            for client_id, connection in self.connections.items():
                result.append({
                    "Id": client_id,
                    "Host": connection.client_host,
                    "Name": connection.client_name,
                    "Conn": connection.connection_info.connect_time.strftime("%Y-%m-%d %H:%M:%S"),
                    "Act": connection.connection_info.last_action.strftime("%Y-%m-%d %H:%M:%S"),
                    "Idle": str(connection.idle_time_sec)
                })
        
        return result 
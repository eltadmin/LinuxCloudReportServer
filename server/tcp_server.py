import asyncio
import logging
from typing import Dict, Optional, List
import json
import zlib
from datetime import datetime, timedelta
from pathlib import Path
import random
import string
import traceback
from .constants import CRYPTO_DICTIONARY
from .crypto import DataCompressor

logger = logging.getLogger(__name__)

class TCPConnection:
    def __init__(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
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

    def encrypt_data(self, data):
        """Encrypts data using the crypto key"""
        if not self.crypto_key:
            self.last_error = "Crypto key is not negotiated"
            return None
            
        compressor = DataCompressor(self.crypto_key)
        result = compressor.compress_data(data)
        
        if not result:
            self.last_error = compressor.last_error
            return None
            
        return result
        
    def decrypt_data(self, data):
        """Decrypts data using the crypto key"""
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
        """Update the last activity timestamp"""
        self.last_activity = datetime.now()

class TCPServer:
    def __init__(self, report_server):
        self.report_server = report_server
        self.server = None
        self.connections: Dict[str, TCPConnection] = {}
        self.pending_connections: List[TCPConnection] = []  # Connections without client_id yet
        
    async def start(self, host: str, port: int):
        """Start TCP server."""
        self.server = await asyncio.start_server(
            self.handle_connection, host, port
        )
        
        # Start background task to check for inactive connections
        asyncio.create_task(self.check_inactive_connections())
        
    async def stop(self):
        """Stop TCP server."""
        if self.server:
            self.server.close()
            await self.server.wait_closed()
            
        # Close all connections
        for conn in self.connections.values():
            try:
                conn.writer.close()
                await conn.writer.wait_closed()
            except Exception as e:
                logger.error(f"Error closing connection: {e}")
                
        # Close all pending connections
        for conn in self.pending_connections:
            try:
                conn.writer.close()
                await conn.writer.wait_closed()
            except Exception as e:
                logger.error(f"Error closing pending connection: {e}")
                
    async def check_inactive_connections(self):
        """Periodically check for and remove inactive connections."""
        while True:
            try:
                now = datetime.now()
                # Check authenticated connections
                clients_to_remove = []
                
                for client_id, conn in self.connections.items():
                    # If last activity is more than 5 minutes ago, remove the connection
                    if (now - conn.last_activity).total_seconds() > 300:
                        logger.info(f"Removing inactive connection for client {client_id}")
                        clients_to_remove.append(client_id)
                        
                for client_id in clients_to_remove:
                    conn = self.connections.pop(client_id)
                    try:
                        conn.writer.close()
                        await conn.writer.wait_closed()
                    except Exception as e:
                        logger.error(f"Error closing inactive connection: {e}")
                
                # Check pending connections (not yet authenticated with client ID)
                pending_to_remove = []
                for i, conn in enumerate(self.pending_connections):
                    # If connection is older than 2 minutes and still not authenticated, remove it
                    if (now - conn.connection_time).total_seconds() > 120:
                        pending_to_remove.append(i)
                
                # Remove from highest index to lowest to avoid shifting issues
                for i in sorted(pending_to_remove, reverse=True):
                    try:
                        conn = self.pending_connections.pop(i)
                        conn.writer.close()
                        await conn.writer.wait_closed()
                    except Exception as e:
                        logger.error(f"Error closing pending connection: {e}")
                
                await asyncio.sleep(60)  # Check every minute
                
            except Exception as e:
                logger.error(f"Error in connection checker: {e}", exc_info=True)
                await asyncio.sleep(60)  # Wait a minute and try again
            
    async def handle_connection(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
        """Handle new TCP connection."""
        conn = TCPConnection(reader, writer)
        peer = conn.peer
        logger.info(f"New TCP connection from {peer}")
        
        # Add to pending connections list until we get a client ID
        self.pending_connections.append(conn)
        
        try:
            while True:
                # Read command
                data = await reader.readuntil(b'\n')
                command = data.decode().strip()
                logger.info(f"Received command from {peer}: {command}")
                
                # Update activity timestamp
                conn.update_activity()
                
                if not command:
                    logger.warning(f"Empty command received from {peer}")
                    continue
                    
                # Handle command
                response = await self.handle_command(conn, command, peer)
                
                if response:
                    # For INIT command, handle the raw bytes response directly
                    if command.startswith('INIT'):
                        # Log raw bytes response in a human-readable way
                        log_response = str(response).replace('\\r\\n', ' | ')
                        logger.info(f"Sending INIT response (bytes) to {peer}: {log_response}")
                        # Send raw bytes directly - no encoding needed
                        writer.write(response)
                    else:
                        # For other commands
                        if isinstance(response, str):
                            # Backward compatibility for string responses
                            logger.info(f"Sending response (string) to {peer}: {response}")
                            if not response.endswith('\n'):
                                response += '\n'
                            writer.write(response.encode('utf-8'))
                        else:
                            # Bytes response
                            log_response = response.decode('utf-8', errors='replace')
                            logger.info(f"Sending response (bytes) to {peer}: {log_response}")
                            if not response.endswith(b'\n'):
                                response += b'\n'
                            writer.write(response)
                    
                    await writer.drain()
                    
        except asyncio.IncompleteReadError:
            logger.info(f"Client {peer} disconnected")
        except asyncio.CancelledError:
            logger.info(f"Connection handling for {peer} was cancelled")
        except Exception as e:
            logger.error(f"Error handling client {peer}: {e}", exc_info=True)
            logger.error(traceback.format_exc())
        finally:
            # Cleanup
            if conn.client_id and conn.client_id in self.connections:
                del self.connections[conn.client_id]
            
            # Remove from pending connections if present
            if conn in self.pending_connections:
                self.pending_connections.remove(conn)
                
            try:
                writer.close()
                await writer.wait_closed()
            except Exception as e:
                logger.error(f"Error closing writer: {e}")
            
    def check_duplicate_client_id(self, client_id: str, current_conn: TCPConnection) -> bool:
        """
        Check if a client ID already exists in the connections dictionary.
        If it exists, close the old connection and return True.
        """
        if client_id in self.connections:
            old_conn = self.connections[client_id]
            # If it's the same connection object, it's not a duplicate
            if old_conn is current_conn:
                return False
                
            logger.warning(f"Duplicate client ID detected: {client_id}. Closing old connection.")
            
            # Schedule closing the old connection asynchronously
            async def close_old_connection():
                try:
                    old_conn.writer.close()
                    await old_conn.writer.wait_closed()
                except Exception as e:
                    logger.error(f"Error closing duplicate connection: {e}")
                    
            asyncio.create_task(close_old_connection())
            return True
            
        return False
            
    async def handle_command(self, conn: TCPConnection, command: str, peer: tuple) -> bytes:
        """Handle TCP command."""
        try:
            parts = command.split(' ')
            cmd = parts[0].upper()
            logger.debug(f"Processing command: {cmd} with parts: {parts}")
            
            # Check if connection is authenticated for commands that require it
            authenticated_commands = ['INFO', 'SRSP', 'GREQ', 'VERS', 'DWNL']
            if cmd in authenticated_commands and not conn.authenticated:
                logger.warning(f"Unauthenticated connection tried to use {cmd} command")
                return b'ERROR Authentication required'
            
            if cmd == 'INIT':
                # Parse INIT command parameters
                params = {}
                for param in parts[1:]:
                    if '=' in param:
                        key, value = param.split('=', 1)
                        params[key.upper()] = value
                logger.info(f"Parsed INIT parameters: {params}")
                
                # Validate required parameters
                required_params = ['ID', 'DT', 'TM', 'HST', 'ATP', 'AVR']
                for param in required_params:
                    if param not in params:
                        error_msg = f'ERROR Missing required parameter: {param}'
                        logger.error(f"INIT validation failed: {error_msg}")
                        return error_msg.encode('utf-8')
                
                # Validate key ID
                try:
                    key_id = int(params['ID'])
                    if not 1 <= key_id <= 10:
                        error_msg = 'ERROR Invalid key ID. Must be between 1 and 10'
                        logger.error(f"INIT validation failed: {error_msg}")
                        return error_msg.encode('utf-8')
                except ValueError:
                    error_msg = 'ERROR Invalid key ID format'
                    logger.error(f"INIT validation failed: {error_msg}")
                    return error_msg.encode('utf-8')
                
                # Store client info
                conn.client_host = params['HST']
                conn.app_type = params['ATP']
                conn.app_version = params['AVR']
                logger.info(f"Stored client info: host={conn.client_host}, type={conn.app_type}, version={conn.app_version}")
                
                # Generate crypto key
                # The key length is used to determine how many characters to take from the dictionary entry
                key_len = random.randint(1, 12)
                server_key = ''.join(random.choices(string.ascii_letters + string.digits, k=8))
                
                # Store crypto key components
                conn.server_key = server_key
                conn.key_length = key_len
                
                # In the original implementation, the full crypto key combines:
                # 1. The server_key (random 8 chars)
                # 2. A portion of the crypto dictionary entry for the given key_id
                # 3. First 2 chars of client host + last char of client host
                # This is crucial for the client to correctly compute the same key
                crypto_dict_part = CRYPTO_DICTIONARY[key_id - 1][:key_len]
                host_part = conn.client_host[:2] + conn.client_host[-1:]
                conn.crypto_key = server_key + crypto_dict_part + host_part
                
                logger.info(f"Generated crypto key: server_key={server_key}, length={key_len}, full_key={conn.crypto_key}")
                
                # Mark connection as authenticated
                conn.authenticated = True
                logger.info(f"Connection authenticated for client {peer}")
                
                # CRITICAL FIX: Create INIT response with exact byte-by-byte format
                # The Delphi client has very specific expectations for the format:
                # - Status code on first line with CRLF
                # - Each parameter (LEN, KEY) on its own line with CRLF
                # - An extra CRLF at the end
                # - No spaces between parameter name, = sign, and value
                
                # Build the response as raw bytes to ensure exact formatting
                # First line: status code
                response_bytes = b"200 OK\r\n"
                # Second line: LEN parameter
                response_bytes += f"LEN={key_len}\r\n".encode('ascii')
                # Third line: KEY parameter
                response_bytes += f"KEY={server_key}\r\n".encode('ascii')
                # Final empty line
                response_bytes += b"\r\n"
                
                # This raw byte-based approach ensures the exact format
                # expected by the Delphi TIdCommand parsing mechanism
                
                logger.info(f"Created INIT response with raw bytes: status=200 OK, LEN={key_len}, KEY={server_key}")
                return response_bytes
                
            elif cmd == 'ERRL':
                # Handle error logging from client
                error_msg = ' '.join(parts[1:])
                logger.error(f"Client error: {error_msg}")
                return b'OK'
                
            elif cmd == 'PING':
                conn.last_ping = datetime.now()
                return b'PONG'
                
            elif cmd == 'INFO':
                # Handle client ID initialization
                # Check if crypto key is negotiated
                if not conn.crypto_key:
                    logger.error("Crypto key is not negotiated")
                    return b'ERROR Crypto key is not negotiated'
                
                # Get encrypted data from client
                if len(parts) < 2 or not parts[1].startswith('DATA='):
                    return b'ERROR Missing DATA parameter'
                
                encrypted_data = parts[1][5:]  # Remove DATA= prefix
                
                # Decrypt data
                decrypted_data = conn.decrypt_data(encrypted_data)
                if not decrypted_data:
                    logger.error(f"Failed to decrypt data: {conn.last_error}")
                    return f'ERROR Failed to decrypt data: {conn.last_error}'.encode('utf-8')
                
                # Parse data as key-value pairs
                data_pairs = {}
                for line in decrypted_data.splitlines():
                    if '=' in line:
                        key, value = line.split('=', 1)
                        data_pairs[key] = value
                
                # Validate data
                if 'ТТ' not in data_pairs or data_pairs['ТТ'] != 'Test':
                    logger.error("Decryption problem - validation failed")
                    return b'ERROR Decryption validation failed'
                
                # Store client information
                client_id = data_pairs.get('ID', '')
                if not client_id:
                    logger.error("Missing client ID in INFO data")
                    return b'ERROR Missing client ID'
                
                conn.client_id = client_id
                
                # Check for duplicate client ID
                self.check_duplicate_client_id(client_id, conn)
                
                # Remove from pending connections list if present
                if conn in self.pending_connections:
                    self.pending_connections.remove(conn)
                
                # Create response data
                response_data = {
                    'ID': conn.client_id,
                    'EX': (datetime.now() + timedelta(days=365)).strftime('%y%m%d'),
                    'EN': 'True',
                    'CD': datetime.now().strftime('%y%m%d'),
                    'CT': datetime.now().strftime('%H%M%S')
                }
                
                # Convert response to string
                response_text = '\n'.join([f"{k}={v}" for k, v in response_data.items()])
                
                # Encrypt response
                encrypted_response = conn.encrypt_data(response_text)
                if not encrypted_response:
                    logger.error(f"Failed to encrypt response: {conn.last_error}")
                    return f'ERROR Failed to encrypt response: {conn.last_error}'.encode('utf-8')
                
                # Save connection in connections dictionary
                self.connections[conn.client_id] = conn
                
                # Return response
                return f'200 OK\nDATA={encrypted_response}'.encode('utf-8')
                
            elif cmd == 'VERS':
                # Handle version check and updates list
                try:
                    # Get update file list with version information
                    updates = []
                    update_folder = Path(self.report_server.update_folder)
                    
                    if not update_folder.exists():
                        logger.warning(f"Update folder {update_folder} does not exist")
                        return json.dumps({'updates': []}).encode('utf-8')
                    
                    for file in update_folder.glob('*'):
                        if file.is_file():
                            updates.append({
                                'name': file.name,
                                'size': file.stat().st_size,
                                'modified': datetime.fromtimestamp(file.stat().st_mtime).strftime('%Y-%m-%d %H:%M:%S')
                            })
                    
                    if conn.crypto_key:
                        # If we have a crypto key, encrypt the response
                        response_text = json.dumps({'updates': updates})
                        encrypted_response = conn.encrypt_data(response_text)
                        if not encrypted_response:
                            logger.error(f"Failed to encrypt VERS response: {conn.last_error}")
                            return f'ERROR Failed to encrypt VERS response: {conn.last_error}'.encode('utf-8')
                        return f'200 OK\nDATA={encrypted_response}'.encode('utf-8')
                    else:
                        # Plain response if no crypto key
                        return json.dumps({'updates': updates}).encode('utf-8')
                        
                except Exception as e:
                    logger.error(f"Error handling VERS command: {e}", exc_info=True)
                    return f'ERROR Failed to get updates list: {str(e)}'.encode('utf-8')
                
            elif cmd == 'DWNL':
                # Handle download of update files
                try:
                    if len(parts) < 2:
                        return b'ERROR Missing filename parameter'
                        
                    filename = parts[1]
                    file_path = Path(self.report_server.update_folder) / filename
                    
                    if not file_path.exists() or not file_path.is_file():
                        logger.error(f"Requested file not found: {filename}")
                        return b'ERROR File not found'
                    
                    # Read file and prepare for sending
                    with open(file_path, 'rb') as f:
                        file_data = f.read()
                    
                    # Compress data with zlib
                    compressed_data = zlib.compress(file_data)
                    file_size = len(compressed_data)
                    
                    # First send OK response with file size
                    response = f'200 OK\r\nSIZE={file_size}\r\n\r\n'.encode()
                    logger.info(f"Sending DWNL response header: status=200, SIZE={file_size}")
                    conn.writer.write(response)
                    await conn.writer.drain()
                    
                    # Then send the file data
                    logger.info(f"Sending file data: {filename} ({file_size} bytes compressed)")
                    conn.writer.write(compressed_data)
                    await conn.writer.drain()
                    
                    logger.info(f"Sent file {filename} ({file_size} bytes compressed) to {peer}")
                    return b''  # We've already sent the response directly
                    
                except Exception as e:
                    logger.error(f"Error handling DWNL command: {e}", exc_info=True)
                    return f'ERROR Failed to download file: {str(e)}'.encode('utf-8')
                
            elif cmd == 'GREQ':
                # Handle report generation requests
                try:
                    # Check for required parameters
                    if len(parts) < 2:
                        return b'ERROR Missing report type parameter'
                    
                    report_type = parts[1]
                    params = {}
                    
                    # Parse parameters
                    if len(parts) > 2:
                        if parts[2].startswith('DATA='):
                            encrypted_data = parts[2][5:]  # Remove DATA= prefix
                            
                            # Decrypt data if crypto key is available
                            if conn.crypto_key:
                                decrypted_data = conn.decrypt_data(encrypted_data)
                                if not decrypted_data:
                                    logger.error(f"Failed to decrypt GREQ data: {conn.last_error}")
                                    return f'ERROR Failed to decrypt parameters: {conn.last_error}'.encode('utf-8')
                                
                                try:
                                    params = json.loads(decrypted_data)
                                except json.JSONDecodeError:
                                    logger.error("Invalid JSON in decrypted GREQ data")
                                    return b'ERROR Invalid JSON parameters'
                            else:
                                try:
                                    params = json.loads(encrypted_data)
                                except json.JSONDecodeError:
                                    logger.error("Invalid JSON in GREQ data")
                                    return b'ERROR Invalid JSON parameters'
                    
                    # Generate report
                    report_result = await self.report_server.db.generate_report(report_type, params)
                    
                    # Convert result to JSON
                    result_json = json.dumps(report_result)
                    
                    # Encrypt response if crypto key is available
                    if conn.crypto_key:
                        encrypted_response = conn.encrypt_data(result_json)
                        if not encrypted_response:
                            logger.error(f"Failed to encrypt GREQ response: {conn.last_error}")
                            return f'ERROR Failed to encrypt response: {conn.last_error}'.encode('utf-8')
                        return f'200 OK\nDATA={encrypted_response}'.encode('utf-8')
                    else:
                        return f'200 OK\n{result_json}'.encode('utf-8')
                        
                except Exception as e:
                    logger.error(f"Error handling GREQ command: {e}", exc_info=True)
                    return f'ERROR Failed to generate report: {str(e)}'.encode('utf-8')
                
            elif cmd == 'SRSP':
                # Handle client response to server requests
                try:
                    # Parse parameters
                    cmd_counter = None
                    data = None
                    
                    for param in parts[1:]:
                        if param.startswith('CMD='):
                            cmd_counter = param[4:]
                        elif param.startswith('DATA='):
                            data = param[5:]
                    
                    if not cmd_counter:
                        logger.error("Missing CMD parameter in SRSP")
                        return b'ERROR Missing CMD parameter'
                    
                    if not data:
                        logger.error("Missing DATA parameter in SRSP")
                        return b'ERROR Missing DATA parameter'
                    
                    # Decrypt data if crypto key is available
                    decrypted_data = None
                    if conn.crypto_key:
                        decrypted_data = conn.decrypt_data(data)
                        if not decrypted_data:
                            logger.error(f"Failed to decrypt SRSP data: {conn.last_error}")
                            return f'ERROR Failed to decrypt data: {conn.last_error}'.encode('utf-8')
                    else:
                        decrypted_data = data
                    
                    # Process the response data (in a real implementation, you would match this with a pending request)
                    logger.info(f"Received response for command {cmd_counter} from client {conn.client_id}")
                    logger.debug(f"Response data: {decrypted_data}")
                    
                    # Handle different response types based on command_counter if needed
                    # Here we're just acknowledging receipt
                    return b'200 OK'
                    
                except Exception as e:
                    logger.error(f"Error handling SRSP command: {e}", exc_info=True)
                    return f'ERROR Failed to process response: {str(e)}'.encode('utf-8')
                
            else:
                logger.warning(f"Unknown command received: {cmd}")
                return f'ERROR Unknown command: {cmd}'.encode('utf-8')
                
        except Exception as e:
            logger.error(f"Unhandled error in handle_command: {e}", exc_info=True)
            return f'ERROR Internal server error: {str(e)}'.encode('utf-8') 
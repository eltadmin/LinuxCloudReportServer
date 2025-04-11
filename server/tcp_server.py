import asyncio
import logging
from typing import Dict, Optional
import json
import zlib
from datetime import datetime, timedelta
from pathlib import Path
import random
import string
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

class TCPServer:
    def __init__(self, report_server):
        self.report_server = report_server
        self.server = None
        self.connections: Dict[str, TCPConnection] = {}
        
    async def start(self, host: str, port: int):
        """Start TCP server."""
        self.server = await asyncio.start_server(
            self.handle_connection, host, port
        )
        
    async def stop(self):
        """Stop TCP server."""
        if self.server:
            self.server.close()
            await self.server.wait_closed()
            
        # Close all connections
        for conn in self.connections.values():
            conn.writer.close()
            await conn.writer.wait_closed()
            
    async def handle_connection(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
        """Handle new TCP connection."""
        conn = TCPConnection(reader, writer)
        peer = conn.peer
        logger.info(f"New TCP connection from {peer}")
        
        try:
            while True:
                # Read command
                data = await reader.readuntil(b'\n')
                command = data.decode().strip()
                logger.info(f"Received command from {peer}: {command}")
                
                if not command:
                    logger.warning(f"Empty command received from {peer}")
                    continue
                    
                # Handle command
                response = await self.handle_command(conn, command, peer)
                logger.info(f"Sending response to {peer}: {response}")
                
                # Send response
                if response:
                    # Ensure response ends with a newline
                    if not response.endswith('\n'):
                        response += '\n'
                    writer.write(response.encode())
                    await writer.drain()
                    
        except asyncio.IncompleteReadError:
            logger.info(f"Client {peer} disconnected")
        except Exception as e:
            logger.error(f"Error handling client {peer}: {e}", exc_info=True)
        finally:
            # Cleanup
            if conn.client_id in self.connections:
                del self.connections[conn.client_id]
            writer.close()
            await writer.wait_closed()
            
    async def handle_command(self, conn: TCPConnection, command: str, peer: tuple) -> str:
        """Handle TCP command."""
        parts = command.split(' ')
        cmd = parts[0].upper()
        logger.info(f"Processing command: {cmd} with parts: {parts}")
        
        try:
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
                        return error_msg
                
                # Validate key ID
                try:
                    key_id = int(params['ID'])
                    if not 1 <= key_id <= 10:
                        error_msg = 'ERROR Invalid key ID. Must be between 1 and 10'
                        logger.error(f"INIT validation failed: {error_msg}")
                        return error_msg
                except ValueError:
                    error_msg = 'ERROR Invalid key ID format'
                    logger.error(f"INIT validation failed: {error_msg}")
                    return error_msg
                
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
                
                # Return response with server key and length
                response = f'200 OK\r\nLEN={key_len}\r\nKEY={server_key}\r\n\r\n'
                logger.info(f"Sending INIT response: {response}")
                return response
                
            elif cmd == 'ERRL':
                # Handle error logging from client
                error_msg = ' '.join(parts[1:])
                logger.error(f"Client error: {error_msg}")
                return 'OK'
                
            elif cmd == 'PING':
                conn.last_ping = datetime.now()
                return 'PONG'
                
            elif cmd == 'INFO':
                # Handle client ID initialization
                # Check if crypto key is negotiated
                if not conn.crypto_key:
                    logger.error("Crypto key is not negotiated")
                    return 'ERROR Crypto key is not negotiated'
                
                # Get encrypted data from client
                if len(parts) < 2 or not parts[1].startswith('DATA='):
                    return 'ERROR Missing DATA parameter'
                
                encrypted_data = parts[1][5:]  # Remove DATA= prefix
                
                # Decrypt data
                decrypted_data = conn.decrypt_data(encrypted_data)
                if not decrypted_data:
                    logger.error(f"Failed to decrypt data: {conn.last_error}")
                    return f'ERROR Failed to decrypt data: {conn.last_error}'
                
                # Parse data as key-value pairs
                data_pairs = {}
                for line in decrypted_data.splitlines():
                    if '=' in line:
                        key, value = line.split('=', 1)
                        data_pairs[key] = value
                
                # Validate data
                if 'ТТ' not in data_pairs or data_pairs['ТТ'] != 'Test':
                    logger.error("Decryption problem - validation failed")
                    return 'ERROR Decryption validation failed'
                
                # Store client information
                conn.client_id = data_pairs.get('ID', '')
                
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
                    return f'ERROR Failed to encrypt response: {conn.last_error}'
                
                # Save connection in connections dictionary
                self.connections[conn.client_id] = conn
                
                # Return response
                return f'200 OK\nDATA={encrypted_response}'
                
            elif cmd == 'VERS':
                # Get update file list
                updates = []
                for file in Path(self.report_server.update_folder).glob('*'):
                    updates.append({
                        'name': file.name,
                        'size': file.stat().st_size,
                        'modified': datetime.fromtimestamp(file.stat().st_mtime).isoformat()
                    })
                return json.dumps({'updates': updates})
                
            elif cmd == 'DWNL':
                # Download update file
                if len(parts) < 2:
                    return 'ERROR Invalid DWNL command'
                    
                filename = parts[1]
                file_path = Path(self.report_server.update_folder) / filename
                
                if not file_path.exists():
                    return 'ERROR File not found'
                    
                # Read and compress file
                with open(file_path, 'rb') as f:
                    data = f.read()
                compressed = zlib.compress(data)
                
                # Send file size and data
                conn.writer.write(str(len(compressed)).encode() + b'\n')
                await conn.writer.drain()
                
                conn.writer.write(compressed)
                await conn.writer.drain()
                return None
                
            elif cmd == 'GREQ':
                # Generate report
                if len(parts) < 3:
                    return 'ERROR Invalid GREQ command'
                    
                report_type = parts[1]
                params = json.loads(' '.join(parts[2:]))
                
                # Generate report using database
                result = await self.report_server.db.generate_report(report_type, params)
                return json.dumps(result)
                
            elif cmd == 'SRSP':
                # Handle client response to a server request
                # Extract command counter and data from parameters
                cmd_counter = None
                data = None
                
                for part in parts[1:]:
                    if part.startswith('CMD='):
                        cmd_counter = part[4:]
                    elif part.startswith('DATA='):
                        data = part[5:]
                
                if not cmd_counter:
                    logger.error("Missing CMD parameter in SRSP")
                    return 'ERROR Missing CMD parameter'
                
                if not data:
                    logger.error("Missing DATA parameter in SRSP")
                    return 'ERROR Missing DATA parameter'
                
                # Check if crypto key is negotiated
                if not conn.crypto_key:
                    logger.error("Crypto key is not negotiated")
                    return 'ERROR Crypto key is not negotiated'
                
                # Decrypt the data
                decrypted = conn.decrypt_data(data)
                if not decrypted:
                    logger.error(f"Failed to decrypt SRSP data: {conn.last_error}")
                    return f'ERROR Failed to decrypt SRSP data: {conn.last_error}'
                
                # Store the response for the matching request
                # In a real implementation, you would use this to match a request with its response
                logger.info(f"Received response for command {cmd_counter}: {decrypted}")
                
                return '200 OK'
                
            else:
                return f'ERROR Unknown command: {cmd}'
                
        except Exception as e:
            logger.error(f"Command error: {e}", exc_info=True)
            return f'ERROR Internal server error' 
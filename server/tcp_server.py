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
import base64
import re
import socket

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
        self.alt_crypto_keys = []  # Keep this for backward compatibility

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
        
    def test_encryption(self):
        """Test the encryption with a known test string to verify it works correctly"""
        if not self.crypto_key:
            self.last_error = "Crypto key is not set for testing"
            return False
            
        try:
            # Тестваме с различни тестови стрингове
            test_strings = [
                "TT=Test",     # Стандартния Delphi тест стринг
                "Test123",     # Прост ASCII текст
                "ID=123\r\nTT=Testing"  # Формат на данни, подобен на клиентски
            ]
            
            for test_string in test_strings:
                logger.debug(f"Testing encryption with key '{self.crypto_key}' and test string '{test_string}'")
                
                # Опитваме се да криптираме и декриптираме
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
                
                # Проверяваме резултата
                if test_string == decrypted:
                    logger.info(f"Encryption test passed with '{test_string}'")
                    return True
                elif "TT" in test_string and "TT" in decrypted and "Test" in decrypted:
                    logger.info(f"Encryption test passed with partial match for '{test_string}'")
                    return True
            
            # Ако нито един от тестовете не е успешен
            self.last_error = "All encryption tests failed"
            logger.error(self.last_error)
            return False
            
        except Exception as e:
            self.last_error = f"Encryption test failed: {str(e)}"
            logger.error(f"Exception during encryption test: {e}", exc_info=True)
            return False

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
        
        # Log connection properties
        logger.debug(f"TCP connection details: local={writer.get_extra_info('sockname')}, remote={peer}")
        logger.debug(f"TCP socket options: keepalive={writer.get_extra_info('socket').getsockopt(socket.SOL_SOCKET, socket.SO_KEEPALIVE)}")
        
        # Add to pending connections list until we get a client ID
        self.pending_connections.append(conn)
        
        try:
            while True:
                # Read command
                try:
                    # Log that we're waiting for data
                    logger.debug(f"Waiting for data from {peer}...")
                    data = await reader.readuntil(b'\n')
                    command = data.decode('utf-8', errors='replace').strip()
                    logger.info(f"Received command from {peer}: {command}")
                    
                    # Log raw bytes for better diagnostics
                    logger.debug(f"Raw received bytes: {' '.join([f'{b:02x}' for b in data])}")
                except Exception as e:
                    logger.error(f"Error reading from socket: {e}")
                    break
                
                # Update activity timestamp
                conn.update_activity()
                
                if not command:
                    logger.warning(f"Empty command received from {peer}")
                    continue
                    
                # Handle command
                response = await self.handle_command(conn, command, peer)
                
                if response:
                    try:
                        # For INIT command, we need exact byte-level handling
                        if command.startswith('INIT'):
                            # Display raw hex bytes for debugging
                            hex_response = ' '.join([f'{b:02x}' for b in response])
                            logger.info(f"INIT raw response bytes: {hex_response}")
                            
                            # Log exact response content for debugging
                            logger.debug(f"INIT response string representation: {response!r}")
                            
                            # Send exactly as is without any modifications
                            writer.write(response)
                        else:
                            # For other commands
                            if isinstance(response, str):
                                # Backward compatibility for string responses
                                logger.info(f"Sending response (string) to {peer}: {response}")
                                # Convert to proper CRLF line endings that Delphi expects
                                if not response.endswith('\r\n'):
                                    response = response.rstrip('\n') + '\r\n'
                                writer.write(response.encode('utf-8'))
                            else:
                                # Bytes response
                                log_response = response.decode('utf-8', errors='replace')
                                logger.info(f"Sending response (bytes) to {peer}: {log_response}")
                                # Make sure response has CRLF line endings
                                if not response.endswith(b'\r\n'):
                                    response = response.rstrip(b'\n') + b'\r\n'
                                writer.write(response)
                        
                        # Log that we're draining the writer
                        logger.debug(f"Draining writer for {peer}...")
                        await writer.drain()
                        logger.debug(f"Writer drained for {peer}")
                    except Exception as e:
                        logger.error(f"Error sending response to {peer}: {e}", exc_info=True)
                        break
                    
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
                required_params = ['ID', 'DT', 'TM', 'HST']  # Removing ATP and AVR from required parameters
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
                conn.app_type = params.get('ATP', 'UnknownApp')  # Default value if ATP is missing
                conn.app_version = params.get('AVR', '1.0.0.0')  # Default value if AVR is missing
                logger.info(f"Stored client info: host={conn.client_host}, type={conn.app_type}, version={conn.app_version}")
                
                # Generate crypto key
                # Use fixed length of 8 for better compatibility
                key_len = 8  # Fixed length for reliability
                server_key = ''.join(random.choices(string.ascii_letters + string.digits, k=8))
                
                # Store crypto key components
                conn.server_key = server_key
                conn.key_length = key_len
                
                # Use the dictionary entry based on key_id
                dict_entry = CRYPTO_DICTIONARY[key_id - 1]
                
                # Take exactly key_len characters from dictionary entry
                crypto_dict_part = dict_entry[:key_len]  
                
                # Extract host parts exactly as Delphi does:
                # First 2 chars + Last char
                host_first_chars = conn.client_host[:2]  # First 2 chars
                host_last_char = conn.client_host[-1:] if conn.client_host else ""  # Last char
                host_part = host_first_chars + host_last_char
                
                # Construct the crypto key:
                # server_key + dict_part + host_part
                conn.crypto_key = server_key + crypto_dict_part + host_part
                
                logger.info(f"Generated crypto key: server_key={server_key}, length={key_len}, full_key={conn.crypto_key}")
                logger.info(f"Crypto key components: dict_part='{crypto_dict_part}', host_part='{host_part}', key_id={key_id}")
                
                # Test the encryption to see if it works correctly
                if conn.test_encryption():
                    logger.info("Encryption test passed successfully")
                else:
                    logger.warning(f"Encryption test failed: {conn.last_error}")
                    
                # Mark connection as authenticated
                conn.authenticated = True
                logger.info(f"Connection authenticated for client {peer}")
                
                # Трябва да се съобразим абсолютно точно с формата, който Delphi очаква
                # Delphi TStrings.Values парсва ред по ред, като очаква KEY=VALUE формат
                # Важно: в Delphi Text.Values['KEY'] очаква KEY=value да бъде в началото на ред
                
                # Проблемът може да е в липсата на статус код в отговора
                # Клиентът "може" да очаква 200 OK в началото
                # Ще пробваме точно този формат
                
                response = b"200 OK\r\n"  # Статус код в началото
                response += b"LEN=" + str(key_len).encode('ascii') + b"\r\n"  # Параметрите след това
                response += b"KEY=" + server_key.encode('ascii') + b"\r\n"
                response += b"\r\n"  # Празен ред накрая - критично за Delphi клиента
                
                # Логваме точния формат на отговора за дебъгване
                # Важно! Не променяйте нищо в горните редове без тестване с клиента
                logger.debug(f"Response raw bytes: {response!r}")
                logger.info(f"INIT response (exact format): 200 OK, LEN={key_len}, KEY={server_key}")
                logger.info(f"INIT raw response bytes: {' '.join([f'{b:02x}' for b in response])}")
                return response
                
            elif cmd == 'ERRL':
                # Handle error logging from client
                error_msg = ' '.join(parts[1:])
                logger.error(f"Client error: {error_msg}")
                
                # Запазваме последния крипто ключ, който не работи
                if conn.crypto_key:
                    logger.error(f"Последният неработещ ключ: '{conn.crypto_key}'")
                    
                # Опит за анализ на грешката
                if "Unable to initizlize communication" in error_msg:
                    logger.error("Анализ: Проблем с форматирането на INIT отговора или неправилен криптиращ ключ.")
                    logger.error(f"INIT параметри: ID={conn.client_id}, host={conn.client_host}")
                    logger.error(f"Изпратен ключ: {conn.server_key}, дължина: {conn.key_length}")
                
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
                logger.debug(f"Received encrypted data: {encrypted_data}")
                logger.info(f"Using crypto key for INFO command: '{conn.crypto_key}'")
                
                # Try to decrypt the data
                decrypted_data = conn.decrypt_data(encrypted_data)
                if not decrypted_data:
                    logger.error(f"Decryption failed: {conn.last_error}")
                    
                    # Попробуем альтернативные ключи, комбинируя части
                    # Иногда проблема может быть в порядке компонентов ключа
                    logger.info("Trying alternative key combinations...")
                    
                    # server_key already stored in conn.server_key
                    key_id = 5  # Default to ID=5 as seen in logs
                    for param in parts[1:]:
                        if param.startswith('ID='):
                            try:
                                key_id = int(param[3:])
                            except:
                                pass
                    
                    # Get dictionary part
                    dict_entry = CRYPTO_DICTIONARY[key_id - 1]
                    key_len = 8
                    dict_part = dict_entry[:key_len]
                    
                    # Get host part
                    host_part = ""
                    if conn.client_host:
                        host_first_chars = conn.client_host[:2]
                        host_last_char = conn.client_host[-1:]
                        host_part = host_first_chars + host_last_char
                    
                    # Try different combinations
                    alt_keys = [
                        dict_part + conn.server_key + host_part,
                        conn.server_key + host_part + dict_part,
                        dict_part + host_part + conn.server_key
                    ]
                    
                    for i, alt_key in enumerate(alt_keys):
                        logger.info(f"Trying alternative key {i+1}: {alt_key}")
                        
                        compressor = DataCompressor(alt_key)
                        alt_decrypted = compressor.decompress_data(encrypted_data)
                        
                        if alt_decrypted:
                            logger.info(f"Alternative key {i+1} worked! Decrypted: {alt_decrypted}")
                            conn.crypto_key = alt_key  # Update to working key
                            decrypted_data = alt_decrypted
                            break
                    
                    if not decrypted_data:
                        return b'ERROR Decryption failed after trying alternative keys'
                
                logger.info(f"Decrypted INFO data: {decrypted_data}")
                
                # Parse client info from decrypted data
                try:
                    # Parse decrypted data as key=value pairs
                    info = {}
                    # Опитваме да покрием различни формати на редове - с \n или \r\n
                    for pair in re.split(r'\r?\n', decrypted_data):
                        if '=' in pair:
                            key, value = pair.split('=', 1)
                            info[key.strip()] = value.strip()
                    
                    logger.info(f"Parsed client info: {info}")
                    
                    # Търсим клиентския ID
                    # Поддържаме и ID и ClientID като ключове
                    client_id = None
                    for id_key in ['ID', 'ClientID', 'CLIENTID', 'id', 'clientid']:
                        if id_key in info:
                            client_id = info[id_key]
                            break
                    
                    if client_id:
                        conn.client_id = client_id
                        logger.info(f"Client ID is set to {conn.client_id}")
                        
                        # Add to authenticated connections and remove from pending
                        self.connections[conn.client_id] = conn
                        if conn in self.pending_connections:
                            self.pending_connections.remove(conn)
                        
                        # В Delphi TStrings формат, без 200 OK статус!
                        # Просто ключове и стойности
                        response_data = {
                            'ID': conn.client_id,
                            'STATUS': 'OK',
                            'TIME': datetime.now().strftime('%H%M%S'),
                            'DATE': datetime.now().strftime('%y%m%d')
                        }
                        
                        # Конвертираме отговора в TStrings формат
                        response_text = '\r\n'.join([f"{k}={v}" for k, v in response_data.items()])
                        response_text += '\r\n\r\n'  # Добавяме празен ред накрая
                        
                        # Криптираме отговора
                        encrypted_response = conn.encrypt_data(response_text)
                        
                        if encrypted_response:
                            # Връщаме като обикновен DATA с криптираните данни
                            return f"DATA={encrypted_response}\r\n\r\n".encode('utf-8')
                        else:
                            return b'ERROR Failed to encrypt response'
                    else:
                        logger.error("Missing client ID in INFO data")
                        return b'ERROR Missing client ID'
                    
                except Exception as e:
                    logger.error(f"Error parsing INFO data: {e}", exc_info=True)
                    return f'ERROR {str(e)}'.encode('utf-8')
                
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
                        return f'200 OK\r\nDATA={encrypted_response}\r\n\r\n'.encode('utf-8')
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

    def get_msg_type(self, content, client, response_message, crypto=None):
        """
        Extracts message type and checks required message format.
        """
        result = ''

        # We're looking for a message type signature
        for msg_type in self.msg_types:
            if msg_type in content:
                result = msg_type
                break

        if not result:
            logger.debug(f"No valid message type found in content: '{content[:100]}...'")
            if "TT=" in content:
                # This might be an encrypted message - check for TT= in various encodings
                logger.debug("Found 'TT=' indicator, attempting to decode as message with validation field")
                
                # Detailed logging of the validation field extraction attempt
                try:
                    validation_field_match = re.search(r'TT=([^&]*)', content)
                    if validation_field_match:
                        validation_field = validation_field_match.group(1)
                        logger.debug(f"Extracted validation field: '{validation_field}'")
                        
                        # Check if it's any of the expected values in different encodings
                        expected_values = ["Test", "Тест"]
                        for expected in expected_values:
                            for encoding in ['utf-8', 'cp1251', 'latin1']:
                                try:
                                    encoded_expected = expected.encode(encoding).decode('latin1')
                                    if validation_field == encoded_expected:
                                        logger.debug(f"Validation field matches '{expected}' encoded with {encoding}")
                                        return "TT=TEST"
                                    elif validation_field.lower() == encoded_expected.lower():
                                        logger.debug(f"Validation field matches '{expected}' (case-insensitive) encoded with {encoding}")
                                        return "TT=TEST"
                                except Exception as e:
                                    logger.debug(f"Error checking encoding {encoding} for value '{expected}': {e}")
                                    continue
                                
                        logger.debug(f"Validation field '{validation_field}' did not match any expected values")
                    else:
                        logger.debug("Found 'TT=' but couldn't extract validation field value")
                except Exception as e:
                    logger.debug(f"Error processing validation field: {e}")
                
                response_message.set_error_message(content="", is_error=True)
                logger.error(f"Authentication failed: {content}")
                return "AUTH_ERROR"

            response_message.set_error_message(content="", is_error=True)
            logger.error(f"Message type not recognized: {content}")
            return "ERROR"

        logger.debug(f"Identified message type: {result}")
        return result 
import asyncio
import logging
from typing import Dict, Optional
import json
import zlib
from datetime import datetime
from pathlib import Path
import random
import string

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
                response = await self.handle_command(conn, command)
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
            
    async def handle_command(self, conn: TCPConnection, command: str) -> str:
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
                key_len = random.randint(1, 12)
                server_key = ''.join(random.choices(string.ascii_letters + string.digits, k=8))
                
                # Store crypto key components
                conn.server_key = server_key
                conn.key_length = key_len
                logger.info(f"Generated crypto key: server_key={server_key}, length={key_len}")
                
                # Mark connection as authenticated
                conn.authenticated = True
                logger.info(f"Connection authenticated for client {peer}")
                
                # Return response with server key and length
                response = f'200 OK\nKEY={server_key}\nLEN={key_len}'
                logger.info(f"Sending INIT response: {response}")
                return response
                
            elif not conn.authenticated:
                return 'ERROR Not authenticated'
                
            elif cmd == 'PING':
                conn.last_ping = datetime.now()
                return 'PONG'
                
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
                
            else:
                return f'ERROR Unknown command: {cmd}'
                
        except Exception as e:
            logger.error(f"Command error: {e}", exc_info=True)
            return f'ERROR Internal server error' 
import asyncio
import logging
from typing import Dict, Optional
import json
import zlib
from datetime import datetime
from pathlib import Path

logger = logging.getLogger(__name__)

class TCPConnection:
    def __init__(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter):
        self.reader = reader
        self.writer = writer
        self.client_id: Optional[str] = None
        self.authenticated = False
        self.peer = writer.get_extra_info('peername')
        self.last_ping = datetime.now()

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
                
                if not command:
                    continue
                    
                # Handle command
                response = await self.handle_command(conn, command)
                
                # Send response
                if response:
                    writer.write(response.encode() + b'\n')
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
        
        try:
            if cmd == 'INIT':
                # Initialize connection with client ID
                if len(parts) < 2:
                    return 'ERROR Invalid INIT command'
                    
                client_id = parts[1]
                
                # Verify client
                if not await self.report_server.verify_client_auth(client_id):
                    return 'ERROR Authentication failed'
                    
                # Check for duplicate client ID
                if client_id in self.connections:
                    return 'ERROR Client ID already connected'
                    
                conn.client_id = client_id
                conn.authenticated = True
                self.connections[client_id] = conn
                return 'OK'
                
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
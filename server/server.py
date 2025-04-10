import asyncio
import logging
from configparser import ConfigParser
from typing import Dict, Optional
from datetime import datetime, timedelta
import aiohttp
import zlib
from pathlib import Path
from .tcp_server import TCPServer
from .http_server import HTTPServer
from .db import Database

logger = logging.getLogger(__name__)

class ReportServer:
    def __init__(self, config: ConfigParser):
        self.config = config
        self.section = 'SRV_1_'
        
        # Load settings
        self.trace_log_enabled = config.getboolean(f'{self.section}COMMON', 'TraceLogEnabled', fallback=False)
        self.update_folder = config.get(f'{self.section}COMMON', 'UpdateFolder', fallback='Updates')
        
        # HTTP settings
        self.http_interface = config.get(f'{self.section}HTTP', 'HTTP_IPInterface', fallback='0.0.0.0')
        self.http_port = config.getint(f'{self.section}HTTP', 'HTTP_Port', fallback=8080)
        
        # TCP settings
        self.tcp_interface = config.get(f'{self.section}TCP', 'TCP_IPInterface', fallback='0.0.0.0')
        self.tcp_port = config.getint(f'{self.section}TCP', 'TCP_Port', fallback=8016)
        
        # Auth settings
        self.auth_server_url = config.get(f'{self.section}AUTHSERVER', 'REST_URL')
        
        # HTTP logins
        self.http_logins = dict(config.items(f'{self.section}HTTPLOGINS'))
        
        # Initialize components
        self.db = Database()
        self.tcp_server = TCPServer(self)
        self.http_server = HTTPServer(self)
        
        # Create required directories
        Path('logs').mkdir(exist_ok=True)
        Path(self.update_folder).mkdir(exist_ok=True)
        
        self.last_cleanup = datetime.now() - timedelta(days=2)
        
    async def start(self):
        """Start all server components."""
        logger.info("Starting Report Server...")
        
        # Initialize database connection
        await self.db.connect()
        
        # Start TCP server
        await self.tcp_server.start(self.tcp_interface, self.tcp_port)
        logger.info(f"TCP server listening on {self.tcp_interface}:{self.tcp_port}")
        
        # Start HTTP server
        await self.http_server.start(self.http_interface, self.http_port)
        logger.info(f"HTTP server listening on {self.http_interface}:{self.http_port}")
        
        # Start maintenance tasks
        asyncio.create_task(self.maintenance_loop())
        
    async def stop(self):
        """Stop all server components."""
        logger.info("Stopping Report Server...")
        
        await self.tcp_server.stop()
        await self.http_server.stop()
        await self.db.disconnect()
        
    async def maintenance_loop(self):
        """Periodic maintenance tasks."""
        while True:
            try:
                # Clean old files every 2 days
                if (datetime.now() - self.last_cleanup).days >= 2:
                    await self.cleanup_old_files()
                    self.last_cleanup = datetime.now()
                
                await asyncio.sleep(3600)  # Check every hour
                
            except Exception as e:
                logger.error(f"Maintenance error: {e}", exc_info=True)
                await asyncio.sleep(60)  # Retry after a minute
                
    async def cleanup_old_files(self):
        """Clean up old files from various directories."""
        try:
            # Clean logs older than 30 days
            cutoff = datetime.now() - timedelta(days=30)
            for file in Path('logs').glob('*.log'):
                if file.stat().st_mtime < cutoff.timestamp():
                    file.unlink()
                    
            # Clean old update files
            for file in Path(self.update_folder).glob('*'):
                if file.stat().st_mtime < cutoff.timestamp():
                    file.unlink()
                    
        except Exception as e:
            logger.error(f"Cleanup error: {e}", exc_info=True)
            
    async def verify_http_login(self, username: str, password: str) -> bool:
        """Verify HTTP login credentials."""
        return username in self.http_logins and self.http_logins[username] == password
        
    async def verify_client_auth(self, client_id: str) -> bool:
        """Verify client authentication via REST API."""
        try:
            async with aiohttp.ClientSession() as session:
                params = {'client_id': client_id}
                async with session.get(self.auth_server_url, params=params) as response:
                    if response.status == 200:
                        data = await response.json()
                        return data.get('authenticated', False)
                    return False
        except Exception as e:
            logger.error(f"Auth verification error: {e}", exc_info=True)
            return False 
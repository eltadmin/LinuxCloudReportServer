"""
Main Server Module for the Linux Cloud Report Server.

This module provides the main server implementation that integrates all components.
"""

import asyncio
import logging
import os
import signal
import sys
from typing import Optional
from configparser import ConfigParser
from datetime import datetime, timedelta
import aiohttp
import zlib
from pathlib import Path
import time
import traceback
from .tcp_server import TCPServer
from .http_server import HTTPServer
from .db import Database

logger = logging.getLogger(__name__)

class ReportServer:
    """
    Main Report Server class that orchestrates all components.
    
    This class manages the TCP server and provides a unified interface for
    starting and stopping the server components.
    """
    
    def __init__(self, config: ConfigParser):
        """
        Initialize the Report Server.
        
        Args:
            config: The configuration parser object
        """
        self.config = config
        self.section = 'SRV_1_'
        self.running = False
        self.db_connected = False
        self.startup_time = None
        
        # Load settings
        try:
            self.trace_log_enabled = config.getboolean(f'{self.section}COMMON', 'TraceLogEnabled', fallback=False)
            self.update_folder = config.get(f'{self.section}COMMON', 'UpdateFolder', fallback='Updates')
            
            # HTTP settings
            self.http_interface = config.get(f'{self.section}HTTP', 'HTTP_IPInterface', fallback='0.0.0.0')
            self.http_port = config.getint(f'{self.section}HTTP', 'HTTP_Port', fallback=8080)
            
            # TCP settings
            self.tcp_interface = config.get(f'{self.section}TCP', 'TCP_IPInterface', fallback='0.0.0.0')
            self.tcp_port = config.getint(f'{self.section}TCP', 'TCP_Port', fallback=8016)
            self.disable_tcp_server = os.getenv('DISABLE_TCP_SERVER', '').lower() in ('true', '1', 'yes')
            
            # Auth settings - prioritize environment variable over INI file
            self.auth_server_url = os.getenv('AUTH_SERVER_URL') or config.get(f'{self.section}AUTHSERVER', 'REST_URL')
            
            # HTTP logins
            self.http_logins = dict(config.items(f'{self.section}HTTPLOGINS'))
            
            if self.disable_tcp_server:
                logger.info(f"TCP server is disabled. Only HTTP on {self.http_interface}:{self.http_port} will be started.")
            else:
                logger.info(f"Configuration loaded successfully: HTTP on {self.http_interface}:{self.http_port}, TCP on {self.tcp_interface}:{self.tcp_port}")
        except Exception as e:
            logger.error(f"Error loading configuration: {e}", exc_info=True)
            raise ValueError(f"Configuration error: {e}")
        
        # Initialize components
        self.db = Database()
        self.tcp_server = TCPServer(self) if not self.disable_tcp_server else None
        self.http_server = HTTPServer(self)
        
        # Create required directories
        try:
            Path('logs').mkdir(exist_ok=True)
            Path(self.update_folder).mkdir(exist_ok=True)
            logger.info(f"Created required directories: logs, {self.update_folder}")
        except Exception as e:
            logger.error(f"Error creating directories: {e}", exc_info=True)
            raise IOError(f"Could not create required directories: {e}")
        
        self.last_cleanup = datetime.now() - timedelta(days=2)
        
    async def start(self):
        """Start the Report Server and all its components."""
        if self.running:
            logger.warning("Server is already running")
            return
        
        try:
            logger.info("Starting Report Server...")
            
            # Initialize database connection with retry logic
            db_connected = False
            retry_count = 0
            max_retries = 5
            retry_delay = 5  # seconds
            
            while not db_connected and retry_count < max_retries:
                try:
                    logger.info(f"Connecting to database (attempt {retry_count + 1}/{max_retries})...")
                    await self.db.connect()
                    db_connected = True
                    self.db_connected = True
                    logger.info("Database connection established")
                except Exception as e:
                    retry_count += 1
                    logger.error(f"Database connection attempt {retry_count} failed: {e}")
                    if retry_count < max_retries:
                        logger.info(f"Retrying in {retry_delay} seconds...")
                        await asyncio.sleep(retry_delay)
                        retry_delay *= 1.5  # Exponential backoff
                    else:
                        logger.critical("Maximum database connection retries reached")
                        raise
            
            # Start TCP server if not disabled
            if not self.disable_tcp_server:
                try:
                    await self.tcp_server.start(self.tcp_interface, self.tcp_port)
                    logger.info(f"TCP server listening on {self.tcp_interface}:{self.tcp_port}")
                except Exception as e:
                    logger.critical(f"Failed to start TCP server: {e}", exc_info=True)
                    await self.db.disconnect()
                    raise RuntimeError(f"TCP server start failed: {e}")
            else:
                logger.info("TCP server is disabled, skipping TCP server startup")
            
            try:
                # Start HTTP server
                await self.http_server.start(self.http_interface, self.http_port)
                logger.info(f"HTTP server listening on {self.http_interface}:{self.http_port}")
            except Exception as e:
                logger.critical(f"Failed to start HTTP server: {e}", exc_info=True)
                # Clean up TCP server if HTTP server fails and TCP server is running
                if not self.disable_tcp_server:
                    await self.tcp_server.stop()
                await self.db.disconnect()
                raise RuntimeError(f"HTTP server start failed: {e}")
            
            # Start maintenance tasks
            self.running = True
            self.startup_time = time.time()
            asyncio.create_task(self.maintenance_loop())
            
            startup_duration = time.time() - self.startup_time
            logger.info(f"Report Server fully started in {startup_duration:.2f} seconds")
            
        except Exception as e:
            logger.error(f"Failed to start Report Server: {e}", exc_info=True)
            await self.stop()
            raise
    
    async def stop(self):
        """Stop the Report Server and all its components."""
        if not self.running:
            logger.debug("Server is not running")
            return
        
        logger.info("Stopping Report Server...")
        
        try:
            # Stop TCP server if not disabled
            if not self.disable_tcp_server:
                try:
                    await self.tcp_server.stop()
                    logger.info("TCP server stopped")
                except Exception as e:
                    error_msg = f"Error stopping TCP server: {e}"
                    logger.error(error_msg, exc_info=True)
            
            try:
                await self.http_server.stop()
                logger.info("HTTP server stopped")
            except Exception as e:
                error_msg = f"Error stopping HTTP server: {e}"
                logger.error(error_msg, exc_info=True)
            
            try:
                await self.db.disconnect()
                logger.info("Database disconnected")
            except Exception as e:
                error_msg = f"Error disconnecting database: {e}"
                logger.error(error_msg, exc_info=True)
            
            self.running = False
            logger.info("Report Server stopped successfully")
            
        except Exception as e:
            logger.error(f"Error stopping Report Server: {e}", exc_info=True)
            raise
    
    async def maintenance_loop(self):
        """Periodic maintenance tasks."""
        if not self.running:
            return
            
        while self.running:
            try:
                now = datetime.now()
                
                # Clean old files every 2 days
                if (now - self.last_cleanup).days >= 2:
                    await self.cleanup_old_files()
                    self.last_cleanup = now
                
                # Check database connection
                if not self.db_connected:
                    try:
                        logger.info("Attempting to reconnect to database...")
                        await self.db.connect()
                        self.db_connected = True
                        logger.info("Database reconnection successful")
                    except Exception as e:
                        logger.error(f"Database reconnection failed: {e}")
                
                # Health check logging
                if not self.disable_tcp_server:
                    active_tcp_clients = len(self.tcp_server.connections)
                else:
                    active_tcp_clients = "N/A (TCP server disabled)"
                logger.info(f"Health check: {active_tcp_clients} active TCP clients, DB connected: {self.db_connected}")
                
                await asyncio.sleep(3600)  # Check every hour
                
            except asyncio.CancelledError:
                logger.info("Maintenance loop cancelled")
                break
            except Exception as e:
                logger.error(f"Maintenance error: {e}", exc_info=True)
                logger.error(traceback.format_exc())
                await asyncio.sleep(60)  # Retry after a minute
                
    async def cleanup_old_files(self):
        """Clean up old files from various directories."""
        try:
            # Clean logs older than 30 days
            logger.info("Starting old file cleanup...")
            cutoff = datetime.now() - timedelta(days=30)
            removed_count = 0
            
            # Clean log files
            log_dir = Path('logs')
            if log_dir.exists():
                for file in log_dir.glob('*.log.*'):
                    if file.stat().st_mtime < cutoff.timestamp():
                        try:
                            file.unlink()
                            removed_count += 1
                        except Exception as e:
                            logger.error(f"Failed to remove old log file {file}: {e}")
            
            # Clean old update files
            update_dir = Path(self.update_folder)
            if update_dir.exists():
                for file in update_dir.glob('*'):
                    if file.is_file() and file.stat().st_mtime < cutoff.timestamp():
                        try:
                            file.unlink()
                            removed_count += 1
                        except Exception as e:
                            logger.error(f"Failed to remove old update file {file}: {e}")
            
            logger.info(f"File cleanup complete: removed {removed_count} old files")
                    
        except Exception as e:
            logger.error(f"Cleanup error: {e}", exc_info=True)
            
    async def verify_http_login(self, username: str, password: str) -> bool:
        """Verify HTTP login credentials."""
        try:
            # Convert username to lowercase for case-insensitive comparison
            username_lower = username.lower()
            
            return any(
                k.lower() == username_lower and v == password
                for k, v in self.http_logins.items()
            )
        except Exception as e:
            logger.error(f"HTTP login verification error: {e}", exc_info=True)
            return False
        
    async def verify_client_auth(self, client_id: str) -> bool:
        """Verify client authentication via REST API."""
        if not client_id:
            logger.error("Empty client ID provided for authentication")
            return False
            
        try:
            logger.info(f"Verifying client authentication for ID: {client_id}")
            
            if not self.auth_server_url:
                logger.warning("No auth server URL configured, authentication bypassed")
                return True
                
            timeout = aiohttp.ClientTimeout(total=10)  # 10 second timeout
            async with aiohttp.ClientSession(timeout=timeout) as session:
                params = {'client_id': client_id}
                try:
                    async with session.get(self.auth_server_url, params=params) as response:
                        if response.status == 200:
                            data = await response.json()
                            authenticated = data.get('authenticated', False)
                            if authenticated:
                                logger.info(f"Client {client_id} authenticated successfully")
                            else:
                                logger.warning(f"Client {client_id} authentication failed")
                            return authenticated
                        else:
                            logger.error(f"Auth API returned non-200 status: {response.status}")
                            return False
                except asyncio.TimeoutError:
                    logger.error(f"Auth server request timed out for client {client_id}")
                    return False
                    
        except Exception as e:
            logger.error(f"Auth verification error for client {client_id}: {e}", exc_info=True)
            return False
            
    def get_server_status(self) -> dict:
        """Get server status information."""
        uptime = None
        if self.startup_time:
            uptime = time.time() - self.startup_time
            
        status_info = {
            "status": "running" if self.running else "stopped",
            "uptime_seconds": uptime,
            "db_connected": self.db_connected,
            "tcp_server_enabled": not self.disable_tcp_server,
            "last_cleanup": self.last_cleanup.isoformat() if self.last_cleanup else None,
            "trace_log_enabled": self.trace_log_enabled
        }
        
        # Add TCP client info if TCP server is enabled
        if not self.disable_tcp_server:
            status_info.update({
                "tcp_clients": len(self.tcp_server.connections),
                "pending_connections": len(self.tcp_server.pending_connections)
            })
            
        return status_info 

class ServerRunner:
    """
    Utility class for running the Report Server with proper signal handling.
    """
    
    def __init__(self, host: str = "0.0.0.0", port: int = 8080):
        """
        Initialize the ServerRunner.
        
        Args:
            host: The hostname or IP address to bind to
            port: The port number to listen on
        """
        self.server = ReportServer(host, port)
        self.loop = asyncio.get_event_loop()
        
        # Set up signal handlers
        for sig in (signal.SIGINT, signal.SIGTERM):
            self.loop.add_signal_handler(sig, lambda: asyncio.create_task(self.shutdown()))
    
    async def run(self):
        """Run the server until stopped."""
        try:
            await self.server.start()
            
            # Keep the server running
            while self.server.running:
                await asyncio.sleep(1)
                
        except Exception as e:
            logger.error(f"Error running server: {e}", exc_info=True)
        finally:
            await self.shutdown()
    
    async def shutdown(self):
        """Shut down the server gracefully."""
        if self.server.running:
            logger.info("Shutting down server...")
            await self.server.stop()
            
            # Stop the event loop
            self.loop.stop()

def run_server(host: str = "0.0.0.0", port: int = 8080):
    """
    Run the Report Server with the given host and port.
    
    Args:
        host: The hostname or IP address to bind to
        port: The port number to listen on
    """
    # Set up logging
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
        handlers=[
            logging.StreamHandler(),
            logging.FileHandler('server.log')
        ]
    )
    
    # Create and run the server
    runner = ServerRunner(host, port)
    
    try:
        # Run the server
        runner.loop.run_until_complete(runner.run())
    except KeyboardInterrupt:
        logger.info("Server stopped by keyboard interrupt")
    except Exception as e:
        logger.error(f"Unexpected error: {e}", exc_info=True)
    finally:
        # Close the event loop
        runner.loop.close()
        logger.info("Server shutdown complete") 
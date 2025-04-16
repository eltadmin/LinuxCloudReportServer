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
from datetime import datetime
from pathlib import Path
import time

from .tcp_server import TCPServer

logger = logging.getLogger(__name__)

class ReportServer:
    """
    Main Report Server class that orchestrates all components.
    
    This class manages the TCP server and provides a unified interface for
    starting and stopping the server components.
    """
    
    def __init__(self, host: str = "0.0.0.0", port: int = 8080):
        """
        Initialize the Report Server.
        
        Args:
            host: The hostname or IP address to bind to
            port: The port number to listen on
        """
        self.host = host
        self.port = port
        self.running = False
        self.startup_time = None
        
        # Create TCP server
        self.tcp_server = TCPServer(self)
        
        # Create required directories
        try:
            Path('logs').mkdir(exist_ok=True)
            logger.info("Created required directories: logs")
        except Exception as e:
            logger.error(f"Error creating directories: {e}", exc_info=True)
            raise IOError(f"Could not create required directories: {e}")
        
    async def start(self):
        """Start the Report Server and all its components."""
        if self.running:
            logger.warning("Server is already running")
            return
        
        try:
            logger.info("Starting Report Server...")
            
            # Start TCP server
            await self.tcp_server.start(self.host, self.port)
            logger.info(f"TCP server listening on {self.host}:{self.port}")
            
            # Start maintenance tasks
            self.running = True
            self.startup_time = time.time()
            
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
            # Stop TCP server
            await self.tcp_server.stop()
            logger.info("TCP server stopped")
            
            self.running = False
            logger.info("Report Server stopped successfully")
            
        except Exception as e:
            logger.error(f"Error stopping Report Server: {e}", exc_info=True)
            raise

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
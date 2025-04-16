"""
Linux Cloud Report Server
This module handles server initialization and lifecycle.
The TCP server functionality has been moved to the Go implementation.
"""

import os
import logging
import time
import threading
import socket
import datetime
import shutil
from pathlib import Path

from server.db import DatabaseConnection
from server.http_server import HTTPServer

logger = logging.getLogger(__name__)

# Constants
UPDATE_DIR = os.environ.get('UPDATE_DIR', 'Updates')
LOG_DIR = os.environ.get('LOG_DIR', 'logs')

class Server:
    """Main server class that coordinates HTTP server (TCP now handled by Go)"""
    
    def __init__(self):
        self.db = None
        self.http_server = None
        self.running = False
        
        # HTTP server configuration
        self.http_host = os.environ.get('HTTP_HOST', '0.0.0.0')
        self.http_port = int(os.environ.get('HTTP_PORT', 8080))
        
        # Create required directories
        self._create_directories()
    
    def _create_directories(self):
        """Create necessary directories if they don't exist"""
        for directory in [LOG_DIR, UPDATE_DIR]:
            os.makedirs(directory, exist_ok=True)
        logger.info(f"Created required directories: {LOG_DIR}, {UPDATE_DIR}")
    
    def start(self):
        """Start the server components"""
        logger.info("Starting Report Server...")
        
        # Connect to the database
        self._connect_to_database()
        
        # Start HTTP server
        self._start_http_server()
        
        # Set server as running
        self.running = True
        
        # Start maintenance thread
        self._start_maintenance_thread()
        
        # Log server startup
        start_time = time.time()
        logger.info(f"Report Server fully started in {time.time() - start_time:.2f} seconds")
    
    def stop(self):
        """Stop all server components"""
        logger.info("Stopping server...")
        self.running = False
        
        # Stop HTTP server
        if self.http_server:
            self.http_server.stop()
            self.http_server = None
        
        # Close database connection
        if self.db:
            self.db.close()
            self.db = None
        
        logger.info("Server stopped")
    
    def _connect_to_database(self):
        """Establish database connection with retries"""
        max_attempts = 5
        attempt = 1
        
        while attempt <= max_attempts:
            logger.info(f"Connecting to database (attempt {attempt}/{max_attempts})...")
            
            try:
                self.db = DatabaseConnection()
                logger.info("Database connection established")
                return
            except Exception as e:
                logger.error(f"Failed to connect to database: {e}")
                if attempt < max_attempts:
                    wait_time = attempt * 2  # Exponential backoff
                    logger.info(f"Retrying in {wait_time} seconds...")
                    time.sleep(wait_time)
                attempt += 1
        
        raise Exception("Failed to connect to database after multiple attempts")
    
    def _start_http_server(self):
        """Start the HTTP server"""
        logger.info(f"Starting HTTP server on {self.http_host}:{self.http_port}")
        self.http_server = HTTPServer(self.http_host, self.http_port, self.db)
        self.http_server.start()
        logger.info(f"HTTP server listening on {self.http_host}:{self.http_port}")
    
    def _start_maintenance_thread(self):
        """Start maintenance thread for cleanup tasks"""
        def maintenance_loop():
            """Periodic maintenance tasks"""
            while self.running:
                try:
                    # Perform cleanup of old files
                    self._cleanup_old_files()
                    
                    # Log health status
                    self._log_health_status()
                    
                    # Sleep for an hour
                    time.sleep(3600)
                except Exception as e:
                    logger.error(f"Error in maintenance thread: {e}")
                    time.sleep(60)  # Sleep a bit on error
        
        thread = threading.Thread(target=maintenance_loop, daemon=True)
        thread.start()
        logger.debug("Maintenance thread started")
    
    def _cleanup_old_files(self):
        """Clean up old log and temporary files"""
        logger.info("Starting old file cleanup...")
        
        try:
            # Clean up logs older than 30 days
            cleanup_count = 0
            current_time = time.time()
            max_age = 30 * 86400  # 30 days in seconds
            
            # Clean up log files
            for file_path in Path(LOG_DIR).glob('*.log'):
                if file_path.is_file():
                    file_age = current_time - file_path.stat().st_mtime
                    if file_age > max_age:
                        file_path.unlink()
                        cleanup_count += 1
            
            logger.info(f"File cleanup complete: removed {cleanup_count} old files")
        
        except Exception as e:
            logger.error(f"Error during file cleanup: {e}")
    
    def _log_health_status(self):
        """Log current health status"""
        try:
            db_connected = bool(self.db and self.db.is_connected())
            
            logger.info(f"Health check: TCP server disabled (now using Go), "
                        f"active TCP clients, DB connected: {db_connected}")
        
        except Exception as e:
            logger.error(f"Error logging health status: {e}") 
#!/usr/bin/env python3
"""
Cloud Report Server - Main module
A Linux implementation of the CloudTcpServer
"""

import os
import signal
import sys
import time
from typing import Dict, List, Optional, Any

from config import ServerConfig
from crypto import check_registration_key
from http_server import HttpServer
from logger import Logger
from tcp_server import TcpServer

class CloudReportServer:
    """Main server class"""
    
    def __init__(self, config_file: str):
        """
        Initialize the server
        
        Args:
            config_file: Path to the configuration file
        """
        # Create logs directory
        self.logs_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "logs")
        os.makedirs(self.logs_dir, exist_ok=True)
        
        # Initialize logger
        self.logger = Logger(self.logs_dir)
        self.logger.log("Cloud Report Server starting...")
        
        # Load configuration
        try:
            self.config = ServerConfig(config_file)
            self.logger.log(f"Configuration loaded from {config_file}")
        except Exception as e:
            self.logger.log(f"Failed to load configuration: {e}")
            sys.exit(1)
        
        # Check registration
        reg_info = self.config.get_registration_info()
        serial = reg_info["serial_number"]
        key = reg_info["key"]
        
        if not check_registration_key(serial, key):
            self.logger.log("Invalid registration key! Exiting.")
            sys.exit(1)
        
        self.logger.log("Registration key validated successfully")
        
        # Initialize server interfaces
        self.tcp_servers = []
        self.http_servers = []
        
        # Get number of server interfaces
        server_count = self.config.get_server_count()
        
        for i in range(1, server_count + 1):
            try:
                # Get server settings
                settings = self.config.get_server_settings(i)
                
                # Create TCP server
                tcp_server = TcpServer(
                    host=settings["tcp_interface"],
                    port=settings["tcp_port"],
                    log_path=self.logs_dir,
                    auth_server_url=settings["auth_server_url"]
                )
                
                # Create HTTP server
                http_server = HttpServer(
                    host=settings["http_interface"],
                    port=settings["http_port"],
                    log_path=self.logs_dir,
                    logins=settings["http_logins"],
                    get_client_func=tcp_server.get_client,
                    get_client_list_func=tcp_server.get_client_list
                )
                
                self.tcp_servers.append(tcp_server)
                self.http_servers.append(http_server)
                
                self.logger.log(f"Server interface {i} initialized")
                
            except Exception as e:
                self.logger.log(f"Failed to initialize server interface {i}: {e}")
    
    def start(self):
        """Start all server interfaces"""
        try:
            # Start TCP servers
            for i, server in enumerate(self.tcp_servers):
                try:
                    server.start()
                    self.logger.log(f"TCP server {i+1} started")
                except Exception as e:
                    self.logger.log(f"Failed to start TCP server {i+1}: {e}")
            
            # Start HTTP servers
            for i, server in enumerate(self.http_servers):
                try:
                    server.start()
                    self.logger.log(f"HTTP server {i+1} started")
                except Exception as e:
                    self.logger.log(f"Failed to start HTTP server {i+1}: {e}")
            
            self.logger.log("Cloud Report Server started")
            
            # Setup signal handlers
            signal.signal(signal.SIGINT, self.stop)
            signal.signal(signal.SIGTERM, self.stop)
            
            # Keep the main thread alive
            while True:
                time.sleep(1)
                
        except Exception as e:
            self.logger.log(f"Error in main server loop: {e}")
            self.stop()
    
    def stop(self, *args):
        """Stop all server interfaces"""
        self.logger.log("Cloud Report Server stopping...")
        
        # Stop HTTP servers
        for i, server in enumerate(self.http_servers):
            try:
                server.stop()
                self.logger.log(f"HTTP server {i+1} stopped")
            except Exception as e:
                self.logger.log(f"Error stopping HTTP server {i+1}: {e}")
        
        # Stop TCP servers
        for i, server in enumerate(self.tcp_servers):
            try:
                server.stop()
                self.logger.log(f"TCP server {i+1} stopped")
            except Exception as e:
                self.logger.log(f"Error stopping TCP server {i+1}: {e}")
        
        self.logger.log("Cloud Report Server stopped")
        sys.exit(0)

if __name__ == "__main__":
    # Get configuration file path
    config_file = os.environ.get(
        "CONFIG_FILE", 
        os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "config", "server.ini")
    )
    
    # Create and start server
    server = CloudReportServer(config_file)
    server.start() 
#!/usr/bin/env python3
"""
Cloud Report Server - Main module
A Linux implementation of the CloudTcpServer
"""

import os
import signal
import sys
import time
import traceback
from typing import Dict, List, Optional, Any

print("Starting CloudReportServer module import...")

try:
    from config import ServerConfig
    from crypto import check_registration_key
    from http_server import HttpServer
    from logger import Logger
    from tcp_server import TcpServer
    print("All modules imported successfully")
except ImportError as e:
    print(f"Error importing modules: {e}", file=sys.stderr)
    print(traceback.format_exc(), file=sys.stderr)
    sys.exit(1)

class CloudReportServer:
    """Main server class"""
    
    def __init__(self, config_file: str):
        """
        Initialize the server
        
        Args:
            config_file: Path to the configuration file
        """
        print(f"Initializing CloudReportServer with config: {config_file}")
        print(f"Current directory: {os.getcwd()}")
        print(f"Files in current directory: {os.listdir('.')}")
        
        try:
            # Create logs directory - handle both Docker and local paths
            if os.path.exists('/app'):
                # Docker path
                self.logs_dir = "/app/logs"
            else:
                # Local path
                self.logs_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "logs")
                
            os.makedirs(self.logs_dir, exist_ok=True)
            print(f"Logs directory created: {self.logs_dir}")
            
            # Initialize logger
            self.logger = Logger(self.logs_dir)
            self.logger.log("Cloud Report Server starting...")
            print("Logger initialized successfully")
            
            # Load configuration
            try:
                print(f"Loading configuration from {config_file}")
                if not os.path.exists(config_file):
                    error_msg = f"Configuration file not found: {config_file}"
                    print(error_msg, file=sys.stderr)
                    self.logger.log(error_msg)
                    
                    # Try alternative paths for Docker
                    alt_paths = [
                        "/app/config/server.ini",
                        os.path.join(os.getcwd(), "config", "server.ini")
                    ]
                    
                    for alt_path in alt_paths:
                        if os.path.exists(alt_path):
                            print(f"Found alternative config at: {alt_path}")
                            config_file = alt_path
                            break
                    else:
                        sys.exit(1)
                
                self.config = ServerConfig(config_file)
                self.logger.log(f"Configuration loaded from {config_file}")
                print("Configuration loaded successfully")
            except Exception as e:
                error_msg = f"Failed to load configuration: {e}"
                self.logger.log(error_msg)
                print(error_msg, file=sys.stderr)
                print(traceback.format_exc(), file=sys.stderr)
                sys.exit(1)
            
            # Check registration
            try:
                print("Checking registration key...")
                reg_info = self.config.get_registration_info()
                serial = reg_info["serial_number"]
                key = reg_info["key"]
                
                print(f"Serial: {serial}, Key: {key}")
                
                if not check_registration_key(serial, key):
                    error_msg = "Invalid registration key! Exiting."
                    self.logger.log(error_msg)
                    print(error_msg, file=sys.stderr)
                    sys.exit(1)
                
                self.logger.log("Registration key validated successfully")
                print("Registration key validated successfully")
            except Exception as e:
                error_msg = f"Error validating registration key: {e}"
                self.logger.log(error_msg)
                print(error_msg, file=sys.stderr)
                print(traceback.format_exc(), file=sys.stderr)
                sys.exit(1)
            
            # Initialize server interfaces
            self.tcp_servers = []
            self.http_servers = []
            
            # Get number of server interfaces
            server_count = self.config.get_server_count()
            print(f"Server interfaces to initialize: {server_count}")
            
            for i in range(1, server_count + 1):
                try:
                    # Get server settings
                    settings = self.config.get_server_settings(i)
                    print(f"Server {i} settings: {settings}")
                    
                    # Create TCP server
                    print(f"Creating TCP server {i}...")
                    tcp_server = TcpServer(
                        host=settings["tcp_interface"],
                        port=settings["tcp_port"],
                        log_path=self.logs_dir,
                        auth_server_url=settings["auth_server_url"]
                    )
                    
                    # Create HTTP server
                    print(f"Creating HTTP server {i}...")
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
                    print(f"Server interface {i} initialized successfully")
                    
                except Exception as e:
                    error_msg = f"Failed to initialize server interface {i}: {e}"
                    self.logger.log(error_msg)
                    print(error_msg, file=sys.stderr)
                    print(traceback.format_exc(), file=sys.stderr)
                    sys.exit(1)
                    
            print("All server interfaces initialized successfully")
            
        except Exception as e:
            # If logger is not initialized yet, print to stderr
            error_msg = f"Fatal error during initialization: {e}"
            if hasattr(self, 'logger'):
                self.logger.log(error_msg)
                self.logger.log(traceback.format_exc())
            
            print(error_msg, file=sys.stderr)
            print(traceback.format_exc(), file=sys.stderr)
            sys.exit(1)
    
    def start(self):
        """Start all server interfaces"""
        print("Starting all server interfaces...")
        
        try:
            # Start TCP servers
            for i, server in enumerate(self.tcp_servers):
                try:
                    print(f"Starting TCP server {i+1}...")
                    server.start()
                    self.logger.log(f"TCP server {i+1} started")
                    print(f"TCP server {i+1} started successfully")
                except Exception as e:
                    error_msg = f"Failed to start TCP server {i+1}: {e}"
                    self.logger.log(error_msg)
                    print(error_msg, file=sys.stderr)
                    print(traceback.format_exc(), file=sys.stderr)
                    self.stop()
                    sys.exit(1)
            
            # Start HTTP servers
            for i, server in enumerate(self.http_servers):
                try:
                    print(f"Starting HTTP server {i+1}...")
                    server.start()
                    self.logger.log(f"HTTP server {i+1} started")
                    print(f"HTTP server {i+1} started successfully")
                except Exception as e:
                    error_msg = f"Failed to start HTTP server {i+1}: {e}"
                    self.logger.log(error_msg)
                    print(error_msg, file=sys.stderr)
                    print(traceback.format_exc(), file=sys.stderr)
                    self.stop()
                    sys.exit(1)
            
            self.logger.log("Cloud Report Server started")
            print("All server interfaces started successfully")
            
            # Setup signal handlers
            signal.signal(signal.SIGINT, self.stop)
            signal.signal(signal.SIGTERM, self.stop)
            
            print("Entering main server loop...")
            
            # Keep the main thread alive
            while True:
                time.sleep(1)
                
        except Exception as e:
            error_msg = f"Error in main server loop: {e}"
            self.logger.log(error_msg)
            print(error_msg, file=sys.stderr)
            print(traceback.format_exc(), file=sys.stderr)
            self.stop()
            sys.exit(1)
    
    def stop(self, *args):
        """Stop all server interfaces"""
        print("Stopping all server interfaces...")
        self.logger.log("Cloud Report Server stopping...")
        
        # Stop HTTP servers
        for i, server in enumerate(self.http_servers):
            try:
                print(f"Stopping HTTP server {i+1}...")
                server.stop()
                self.logger.log(f"HTTP server {i+1} stopped")
                print(f"HTTP server {i+1} stopped successfully")
            except Exception as e:
                error_msg = f"Error stopping HTTP server {i+1}: {e}"
                self.logger.log(error_msg)
                print(error_msg, file=sys.stderr)
                print(traceback.format_exc(), file=sys.stderr)
        
        # Stop TCP servers
        for i, server in enumerate(self.tcp_servers):
            try:
                print(f"Stopping TCP server {i+1}...")
                server.stop()
                self.logger.log(f"TCP server {i+1} stopped")
                print(f"TCP server {i+1} stopped successfully")
            except Exception as e:
                error_msg = f"Error stopping TCP server {i+1}: {e}"
                self.logger.log(error_msg)
                print(error_msg, file=sys.stderr)
                print(traceback.format_exc(), file=sys.stderr)
        
        self.logger.log("Cloud Report Server stopped")
        print("Cloud Report Server stopped successfully")
        sys.exit(0)

if __name__ == "__main__":
    print("=== Cloud Report Server ===")
    print(f"Python version: {sys.version}")
    print(f"Current directory: {os.getcwd()}")
    
    # Get configuration file path
    config_file = os.environ.get("CONFIG_FILE", None)
    if not config_file:
        # Try to find the configuration file
        possible_paths = [
            os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "config", "server.ini"),
            "/app/config/server.ini",
            os.path.join(os.getcwd(), "config", "server.ini")
        ]
        
        for path in possible_paths:
            if os.path.exists(path):
                config_file = path
                print(f"Found configuration file: {path}")
                break
        else:
            print("Configuration file not found!", file=sys.stderr)
            sys.exit(1)
    
    print(f"Using configuration file: {config_file}")
    
    # Create and start server
    try:
        print("Creating CloudReportServer instance...")
        server = CloudReportServer(config_file)
        
        print("Starting CloudReportServer...")
        server.start()
    except Exception as e:
        print(f"Fatal error: {e}", file=sys.stderr)
        print(traceback.format_exc(), file=sys.stderr)
        sys.exit(1) 
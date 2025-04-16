#!/usr/bin/env python3
"""
Linux Cloud Report Server - Main Entry Point
This server is now primarily for HTTP functionality.
The TCP server has been moved to the Go implementation.
"""

import os
import sys
import logging
import logging.handlers
import signal
import argparse
from server.server import Server

# Configure logging
def setup_logging(debug=False):
    log_level = logging.DEBUG if debug else logging.INFO
    log_format = '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    
    # Create logs directory if it doesn't exist
    os.makedirs('logs', exist_ok=True)
    
    # Configure root logger
    root_logger = logging.getLogger()
    root_logger.setLevel(log_level)
    
    # Console handler
    console = logging.StreamHandler()
    console.setLevel(log_level)
    console.setFormatter(logging.Formatter(log_format))
    root_logger.addHandler(console)
    
    # File handler with rotation
    file_handler = logging.handlers.RotatingFileHandler(
        'logs/server.log',
        maxBytes=10*1024*1024,  # 10MB
        backupCount=5
    )
    file_handler.setLevel(log_level)
    file_handler.setFormatter(logging.Formatter(log_format))
    root_logger.addHandler(file_handler)
    
    return root_logger

def main():
    # Parse command line arguments
    parser = argparse.ArgumentParser(description='Linux Cloud Report Server (HTTP only)')
    parser.add_argument('--debug', action='store_true', help='Enable debug logging')
    args = parser.parse_args()
    
    # Setup logging
    logger = setup_logging(debug=args.debug)
    logger.info("Starting Linux Cloud Report Server (HTTP Only - Go TCP Server)")
    
    # Create server instance
    server = Server()
    
    # Handle graceful shutdown
    def signal_handler(sig, frame):
        logger.info(f"Received signal {sig}, shutting down...")
        server.stop()
        sys.exit(0)
    
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    try:
        # Start the server
        server.start()
        logger.info("Server started successfully")
        
        # Keep main thread alive
        while True:
            signal.pause()
    except Exception as e:
        logger.error(f"Server failed to start: {e}", exc_info=True)
        sys.exit(1)

if __name__ == "__main__":
    main() 
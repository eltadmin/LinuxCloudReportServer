"""
Main entry point for the Linux Cloud Report Server.

This module provides the main entry point for starting the server.
"""

import asyncio
import logging
import os
import signal
import sys
from pathlib import Path
import argparse
import logging.handlers

from server import run_server

# Configure logging with rotation
def setup_logging():
    """Set up logging with file rotation and console output."""
    log_dir = Path('logs')
    log_dir.mkdir(exist_ok=True)
    
    # Create formatter
    log_formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
    
    # Create rotating file handler (10MB per file, keep 10 files maximum)
    rotating_handler = logging.handlers.RotatingFileHandler(
        'logs/server.log',
        maxBytes=10*1024*1024,  # 10MB
        backupCount=10,
        encoding='utf-8'
    )
    rotating_handler.setFormatter(log_formatter)
    
    # Create console handler
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(log_formatter)
    
    # Configure root logger
    logging.basicConfig(
        level=logging.INFO,
        handlers=[rotating_handler, console_handler]
    )

def parse_args():
    """Parse command line arguments."""
    parser = argparse.ArgumentParser(description="Linux Cloud Report Server")
    parser.add_argument("--host", default="0.0.0.0", help="Host IP to bind to (default: 0.0.0.0)")
    parser.add_argument("--port", type=int, default=8080, help="Port number to listen on (default: 8080)")
    parser.add_argument("--log-level", choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
                        default="INFO", help="Log level (default: INFO)")
    return parser.parse_args()

def main():
    """Main entry point for the server."""
    # Parse command line arguments
    args = parse_args()
    
    # Set up logging
    setup_logging()
    
    # Set log level from arguments
    logging.getLogger().setLevel(getattr(logging, args.log_level))
    
    logger = logging.getLogger(__name__)
    logger.info("Starting Linux Cloud Report Server...")
    logger.info(f"Host: {args.host}, Port: {args.port}, Log level: {args.log_level}")
    
    # Run the server
    try:
        run_server(args.host, args.port)
    except KeyboardInterrupt:
        logger.info("Server shutdown requested by keyboard interrupt")
    except Exception as e:
        logger.error(f"Fatal error: {e}", exc_info=True)
        return 1
        
    return 0

if __name__ == '__main__':
    sys.exit(main()) 
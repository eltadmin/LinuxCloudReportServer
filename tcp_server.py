#!/usr/bin/env python3
"""
TCP Server Entrypoint

This script serves as a simple entry point for the TCP server component.
"""

import logging
import sys
from server import run_server

if __name__ == "__main__":
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
        handlers=[
            logging.StreamHandler(),
            logging.FileHandler('server.log')
        ]
    )
    
    logging.info("Starting TCP server...")
    run_server("0.0.0.0", 8080) 
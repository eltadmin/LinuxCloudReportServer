#!/usr/bin/env python3
"""
Simple TCP server for debugging INIT responses with Delphi clients.
This server will respond to INIT commands with different formats to see which one works.
"""

import asyncio
import logging
import sys
import os
import time

# Configure logging
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger("debug_init")

VERSIONS = [
    # Version 1: Basic format
    b"200 OK\r\nLEN=8\r\nKEY=DebugKey\r\n\r\n",
    
    # Version 2: No empty line at end
    b"200 OK\r\nLEN=8\r\nKEY=DebugKey\r\n",
    
    # Version 3: Different line endings - LF only
    b"200 OK\nLEN=8\nKEY=DebugKey\n\n",
    
    # Version 4: Different line endings - mixed
    b"200 OK\r\nLEN=8\nKEY=DebugKey\r\n\n",
    
    # Version 5: Adding spaces between key-value
    b"200 OK\r\nLEN = 8\r\nKEY = DebugKey\r\n\r\n",
    
    # Version 6: With content-length
    b"200 OK\r\nContent-Length: 28\r\nLEN=8\r\nKEY=DebugKey\r\n\r\n",
    
    # Version 7: TStrings format (explicit name-value pairs)
    b"LEN=8\r\nKEY=DebugKey\r\n\r\n",
    
    # Version 8: HTTP-like with status line 
    b"HTTP/1.1 200 OK\r\nLEN=8\r\nKEY=DebugKey\r\n\r\n",
    
    # Version 9: Completely different format
    b"OK LEN=8 KEY=DebugKey\r\n\r\n"
]

# Current version being tested
CURRENT_VERSION = int(os.environ.get("DEBUG_VERSION", "1"))

def get_test_response():
    """Create a test INIT response with exact format Delphi expects"""
    if CURRENT_VERSION > len(VERSIONS):
        logger.warning(f"Invalid version {CURRENT_VERSION}, using version 1")
        CURRENT_VERSION = 1
        
    logger.info(f"Using test response version {CURRENT_VERSION}")
    logger.info(f"Response content: {VERSIONS[CURRENT_VERSION-1]}")
    logger.info(f"Response hex: {' '.join([f'{b:02x}' for b in VERSIONS[CURRENT_VERSION-1]])}")
    
    return VERSIONS[CURRENT_VERSION-1]

async def handle_client(reader, writer):
    """Handle TCP connection - respond to INIT with test response"""
    addr = writer.get_extra_info('peername')
    logger.info(f"Connection from {addr}")
    
    try:
        while True:
            data = await reader.readuntil(b'\n')
            cmd = data.decode().strip()
            logger.info(f"Received from {addr}: {cmd}")
            
            if cmd.startswith("INIT"):
                # Extract ID from INIT command
                key_id = None
                for part in cmd.split():
                    if part.startswith("ID="):
                        key_id = part.split("=")[1]
                        break
                        
                logger.info(f"Received INIT with ID={key_id}")
                
                # Send test response
                response = get_test_response()
                logger.info(f"Sending response: {response}")
                writer.write(response)
                await writer.drain()
                
                # Wait for client's next command
                try:
                    next_cmd = await asyncio.wait_for(reader.readuntil(b'\n'), timeout=5.0)
                    logger.info(f"Client response: {next_cmd.decode().strip()}")
                except asyncio.TimeoutError:
                    logger.warning("No response from client within timeout")
                    break
                    
            elif cmd.startswith("ERRL"):
                logger.error(f"Client reported error: {cmd}")
                writer.write(b"OK\r\n")
                await writer.drain()
                break
                
            else:
                logger.info(f"Unknown command: {cmd}")
                writer.write(b"ERROR Unknown command\r\n")
                await writer.drain()
    except asyncio.IncompleteReadError:
        logger.info(f"Client {addr} disconnected")
    except Exception as e:
        logger.error(f"Error handling client: {e}", exc_info=True)
    finally:
        writer.close()
        await writer.wait_closed()
        logger.info(f"Connection closed for {addr}")

async def run_server():
    """Run a simple test server that responds with a fixed INIT response"""
    server = await asyncio.start_server(
        handle_client, '0.0.0.0', 8016
    )
    
    addr = server.sockets[0].getsockname()
    logger.info(f"Serving on {addr}")
    
    async with server:
        await server.serve_forever()

if __name__ == "__main__":
    logger.info(f"Starting debug INIT test server with version {CURRENT_VERSION}")
    try:
        asyncio.run(run_server())
    except KeyboardInterrupt:
        logger.info("Server stopped by user") 
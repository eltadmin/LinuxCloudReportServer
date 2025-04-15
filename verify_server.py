#!/usr/bin/env python3
"""
Verify Server Script

This script connects to the server and sends a test INIT command to check 
that the server is running and responding correctly after updates.
"""

import socket
import time
import sys
import logging

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger('verify_server')

# Default connection parameters
DEFAULT_HOST = '127.0.0.1'
DEFAULT_PORT = 8016

def connect_to_server(host, port, timeout=5):
    """Connect to the server and return the socket"""
    logger.info(f"Connecting to {host}:{port}...")
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(timeout)
        sock.connect((host, port))
        logger.info("Connected successfully")
        return sock
    except Exception as e:
        logger.error(f"Failed to connect: {e}")
        return None

def send_init_command(sock):
    """Send an INIT command to the server and return the response"""
    logger.info("Sending INIT command...")
    init_cmd = "INIT ID=1 DT=250415 TM=123045 HST=TEST-HOST-VERIFY ATP=VerifyClient AVR=1.0.0\n"
    try:
        sock.sendall(init_cmd.encode('ascii'))
        logger.info(f"Sent: {init_cmd.strip()}")
        
        # Wait for response
        response = b""
        start_time = time.time()
        while time.time() - start_time < 5:  # 5 second timeout
            try:
                data = sock.recv(1024)
                if not data:
                    break
                response += data
                if b'\n' in response or b'\r\n' in response:
                    break
            except socket.timeout:
                break
                
        logger.info(f"Received: {response}")
        return response
    except Exception as e:
        logger.error(f"Error sending command: {e}")
        return None

def check_response(response):
    """Check if the response is valid"""
    if not response:
        logger.error("No response received")
        return False
        
    try:
        response_text = response.decode('ascii', errors='replace')
        logger.info(f"Response text: {response_text}")
        
        if 'LEN=' in response_text and 'KEY=' in response_text:
            logger.info("✓ Response contains LEN and KEY parameters")
            return True
        else:
            logger.error("✗ Response does not contain expected parameters")
            return False
    except Exception as e:
        logger.error(f"Error parsing response: {e}")
        return False

def main():
    """Main function"""
    # Parse command line arguments
    host = DEFAULT_HOST
    port = DEFAULT_PORT
    
    if len(sys.argv) > 1:
        host = sys.argv[1]
    if len(sys.argv) > 2:
        port = int(sys.argv[2])
    
    logger.info(f"Server verification starting for {host}:{port}")
    
    # Connect to server
    sock = connect_to_server(host, port)
    if not sock:
        logger.error("Verification failed: could not connect to server")
        return 1
        
    try:
        # Send INIT command
        response = send_init_command(sock)
        
        # Check response
        if check_response(response):
            logger.info("✓ Server verification successful!")
            return 0
        else:
            logger.error("✗ Server verification failed: invalid response")
            return 1
    finally:
        sock.close()
        logger.info("Connection closed")

if __name__ == '__main__':
    sys.exit(main()) 
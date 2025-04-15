#!/usr/bin/env python3
"""
TCP Proxy for debugging client-server communication

This script creates a proxy between the client and server to capture
and analyze all the traffic between them. It helps diagnose protocol
issues by showing the exact data exchange.
"""

import socket
import sys
import threading
import time
from datetime import datetime
import binascii

# Configuration
LISTEN_HOST = '0.0.0.0'    # Interface to listen on
LISTEN_PORT = 8017          # Port to listen on
TARGET_HOST = '127.0.0.1'   # Real server address
TARGET_PORT = 8016          # Real server port

# Logging
LOG_FILE = 'proxy_log.txt'

def hexdump(data):
    """Format data as hex dump with ASCII representation"""
    result = []
    for i in range(0, len(data), 16):
        chunk = data[i:i+16]
        hex_str = ' '.join(f'{b:02x}' for b in chunk)
        ascii_str = ''.join(chr(b) if 32 <= b <= 126 else '.' for b in chunk)
        result.append(f"{i:04x}: {hex_str:<48} {ascii_str}")
    return '\n'.join(result)

def log_data(direction, data, client_addr=None):
    """Log data to file and console"""
    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S.%f')
    
    # Format message
    if direction == "CLIENT -> SERVER":
        prefix = f"[{timestamp}] {client_addr} -> SERVER"
    else:
        prefix = f"[{timestamp}] SERVER -> {client_addr}"
    
    message = f"{prefix}\n"
    
    # Add data in multiple formats
    try:
        # ASCII representation
        ascii_data = data.decode('ascii', errors='replace')
        message += f"ASCII: '{ascii_data}'\n"
    except:
        message += "ASCII: Could not decode\n"
    
    # Hex representation
    hex_data = ' '.join(f'{b:02x}' for b in data)
    message += f"HEX: {hex_data}\n"
    
    # Detailed hex dump
    message += f"DUMP:\n{hexdump(data)}\n"
    message += "-" * 80 + "\n"
    
    # Print to console
    print(message)
    
    # Write to log file
    try:
        with open(LOG_FILE, 'a') as f:
            f.write(message + "\n")
    except Exception as e:
        print(f"Error writing to log file: {e}")

def handle_client(client_socket, client_addr):
    """Handle communication between client and server"""
    print(f"[+] New connection from {client_addr}")
    
    try:
        # Connect to the target server
        server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        server_socket.connect((TARGET_HOST, TARGET_PORT))
        print(f"[+] Connected to {TARGET_HOST}:{TARGET_PORT}")
        
        # Start two threads to handle bidirectional communication
        client_to_server = threading.Thread(
            target=forward_data,
            args=(client_socket, server_socket, "CLIENT -> SERVER", client_addr),
            daemon=True
        )
        
        server_to_client = threading.Thread(
            target=forward_data,
            args=(server_socket, client_socket, "SERVER -> CLIENT", client_addr),
            daemon=True
        )
        
        client_to_server.start()
        server_to_client.start()
        
        # Wait for threads to finish
        client_to_server.join()
        server_to_client.join()
        
    except Exception as e:
        print(f"[-] Error: {e}")
    finally:
        client_socket.close()
        print(f"[+] Connection with {client_addr} closed")

def forward_data(source, destination, direction, client_addr):
    """Forward data from source to destination and log it"""
    try:
        buffer_size = 4096
        while True:
            # Receive data
            data = source.recv(buffer_size)
            if not data:
                break
                
            # Log received data
            log_data(direction, data, client_addr)
            
            # Forward data
            destination.sendall(data)
            
    except Exception as e:
        print(f"[-] Error in {direction}: {e}")
    finally:
        source.close()
        destination.close()

def main():
    """Main function"""
    # Create log file and clear it
    with open(LOG_FILE, 'w') as f:
        f.write(f"TCP Proxy started at {datetime.now()}\n")
        f.write(f"Listening on {LISTEN_HOST}:{LISTEN_PORT}\n")
        f.write(f"Forwarding to {TARGET_HOST}:{TARGET_PORT}\n\n")
    
    try:
        # Set up the server
        server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        server.bind((LISTEN_HOST, LISTEN_PORT))
        server.listen(5)
        
        print(f"[*] Listening on {LISTEN_HOST}:{LISTEN_PORT}")
        print(f"[*] Forwarding to {TARGET_HOST}:{TARGET_PORT}")
        
        # Accept connections
        while True:
            client_socket, addr = server.accept()
            client_handler = threading.Thread(
                target=handle_client,
                args=(client_socket, addr),
                daemon=True
            )
            client_handler.start()
            
    except KeyboardInterrupt:
        print("\n[*] Shutting down the proxy")
        server.close()
        sys.exit(0)
    
if __name__ == "__main__":
    # Allow command line arguments to override defaults
    if len(sys.argv) >= 3:
        TARGET_HOST = sys.argv[1]
        TARGET_PORT = int(sys.argv[2])
    if len(sys.argv) >= 5:
        LISTEN_HOST = sys.argv[3]
        LISTEN_PORT = int(sys.argv[4])
    
    main() 
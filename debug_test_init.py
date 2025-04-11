#!/usr/bin/env python3
"""
Debug tool to test INIT command processing for the Delphi client
"""
import socket
import time
import random
import string
from pathlib import Path

# Copy of the constants to make this script standalone
CRYPTO_DICTIONARY = [
    '123hk12h8dcal',
    'FT676Ugug6sFa',
    'a6xbBa7A8a9la',
    'qMnxbtyTFvcqi',
    'cx7812vcxFRCC',
    'bab7u682ftysv',
    'YGbsux&Ygsyxg',  # This must match exactly what the client expects
    'MSN><hu8asG&&',
    '23yY88syHXvvs',
    '987sX&sysy891'
]

def create_test_init_response(key_id, client_host):
    """Create a test INIT response with exact format Delphi expects"""
    # Generate the same server key for testing consistency
    key_len = 12  # Fixed for testing
    server_key = "TestKey12"  # Fixed for testing
    
    # Calculate the full crypto key
    crypto_dict_part = CRYPTO_DICTIONARY[key_id - 1][:key_len]
    host_part = client_host[:2] + client_host[-1:]
    full_key = server_key + crypto_dict_part + host_part
    
    print(f"Generated key components:")
    print(f"  server_key: {server_key}")
    print(f"  key_length: {key_len}")
    print(f"  dictionary entry[{key_id}]: {CRYPTO_DICTIONARY[key_id - 1]}")
    print(f"  crypto_dict_part: {crypto_dict_part}")
    print(f"  host_part: {host_part}")
    print(f"  full_key: {full_key}")
    
    # Format the response exactly as the Delphi client expects
    status_line = b"200 OK\r\n"
    response = status_line
    response += b"LEN=" + str(key_len).encode('ascii') + b"\r\n"
    response += b"KEY=" + server_key.encode('ascii') + b"\r\n"
    response += b"\r\n"  # Blank line at the end
    
    # Display the raw bytes for debugging
    print("\nRaw response bytes:")
    print(' '.join([f'{b:02x}' for b in response]))
    
    return response

def run_test_server():
    """Run a simple test server that responds with a fixed INIT response"""
    host = '0.0.0.0'
    port = 8016
    
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        s.bind((host, port))
        s.listen()
        print(f"Test server listening on {host}:{port}")
        
        while True:
            conn, addr = s.accept()
            with conn:
                print(f"Connection from {addr}")
                data = conn.recv(1024)
                if not data:
                    continue
                
                cmd = data.decode('ascii').strip()
                print(f"Received: {cmd}")
                
                if cmd.startswith('INIT'):
                    # Parse the INIT command
                    parts = cmd.split(' ')
                    params = {}
                    for part in parts[1:]:
                        if '=' in part:
                            key, value = part.split('=', 1)
                            params[key] = value
                    
                    # Get key ID
                    key_id = int(params.get('ID', '1'))
                    client_host = params.get('HST', 'UNKNOWN')
                    
                    # Create response
                    response = create_test_init_response(key_id, client_host)
                    print(f"Sending response...")
                    conn.sendall(response)
                else:
                    conn.sendall(b"ERROR Unknown command\r\n")

if __name__ == "__main__":
    print("Starting test server for debugging INIT commands")
    run_test_server() 
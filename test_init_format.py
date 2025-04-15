#!/usr/bin/env python3
"""
Test script for INIT command response format.
This script connects to the server, sends an INIT command, and displays 
the raw response to help verify that it's in the right format for the Delphi client.
"""

import socket
import time
import sys
import random
import string

# Server details (adjust as needed)
HOST = '127.0.0.1'  # localhost
PORT = 8016         # default TCP port

def main():
    print(f"Connecting to server at {HOST}:{PORT}...")
    
    # Create socket
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(10)  # 10 second timeout
    
    try:
        # Connect to server
        sock.connect((HOST, PORT))
        print("Connected to server!")
        
        # Generate INIT command similar to what the Delphi client would send
        hostname = f"TEST-HOST-{random.randint(1, 100)}"
        init_cmd = f"INIT ID=1 DT=250415 TM=123045 HST={hostname} ATP=TestClient AVR=1.0.0\n"
        
        print(f"Sending INIT command: {init_cmd.strip()}")
        sock.sendall(init_cmd.encode('ascii'))
        
        # Wait for response
        print("Waiting for response...")
        response = b""
        while True:
            data = sock.recv(1024)
            if not data:
                break
            response += data
            # If we have a complete response, break
            if b'\n' in response or b'\r\n' in response:
                break
        
        print("\n=== Raw Response ===")
        print(f"Bytes: {response}")
        print(f"Hex: {response.hex(' ')}")
        
        # Try to decode as ASCII
        try:
            ascii_response = response.decode('ascii')
            print(f"ASCII: '{ascii_response}'")
            
            # Check if the response contains LEN and KEY
            if 'LEN=' in ascii_response and 'KEY=' in ascii_response:
                print("\n✅ Response contains LEN and KEY parameters")
                
                # Parse the response to extract values
                response_lines = ascii_response.replace('\r\n', '\n').split('\n')
                for line in response_lines:
                    if line.strip():
                        print(f"Line: '{line}'")
                        if '=' in line:
                            key, value = line.split('=', 1)
                            print(f"  Parameter: {key} = {value}")
                
                # Simulate how a Delphi client would parse this
                print("\n=== Delphi Parsing Simulation ===")
                
                # In Delphi, TIdReply.Text would contain the response lines
                # And TIdReply.Text.Values['KEY'] would access the key value
                values = {}
                for line in response_lines:
                    if '=' in line:
                        key, value = line.split('=', 1)
                        values[key] = value
                
                len_value = values.get('LEN', 'NOT FOUND')
                key_value = values.get('KEY', 'NOT FOUND')
                
                print(f"LEN value: {len_value}")
                print(f"KEY value: {key_value}")
                
                if len_value != 'NOT FOUND' and key_value != 'NOT FOUND':
                    print("\n✅ Delphi client should be able to parse this response correctly")
                else:
                    print("\n❌ Delphi client might have trouble parsing this response")
            else:
                print("\n❌ Response does not contain LEN and KEY parameters")
            
        except UnicodeDecodeError:
            print("Response contains non-ASCII characters")
        
    except socket.error as e:
        print(f"Socket error: {e}")
    except Exception as e:
        print(f"Error: {e}")
    finally:
        sock.close()
        print("\nConnection closed")

if __name__ == "__main__":
    # Allow overriding host and port from command line
    if len(sys.argv) > 1:
        HOST = sys.argv[1]
    if len(sys.argv) > 2:
        PORT = int(sys.argv[2])
        
    main() 
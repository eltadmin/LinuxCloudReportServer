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
import struct

# Server details (adjust as needed)
HOST = '10.150.40.8'  # localhost
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
            if b'\n' in response or b'\r\n' in response or len(response) > 4:
                # For binary formats, we need to check if we have received all data
                if len(response) >= 1 and response[0] <= 3:  # Format 7
                    # For format 7, first byte is count, check if we have expected content
                    num_lines = response[0]
                    # Check if we have enough CRLF sequences
                    crlf_count = response.count(b'\r\n')
                    if crlf_count >= num_lines:
                        break
                elif len(response) >= 4:  # Format 8 possibly
                    try:
                        count = struct.unpack('<I', response[:4])[0]
                        if count <= 10:  # Sanity check - shouldn't have too many strings
                            # For format 8, check if we've received all the strings
                            offset = 4
                            complete = True
                            for _ in range(count):
                                if offset + 4 > len(response):
                                    complete = False
                                    break
                                str_len = struct.unpack('<I', response[offset:offset+4])[0]
                                offset += 4
                                if offset + str_len > len(response):
                                    complete = False
                                    break
                                offset += str_len
                            if complete:
                                break
                    except struct.error:
                        # Not format 8 or malformed, continue with normal checks
                        pass
                else:
                    break
        
        print("\n=== Raw Response ===")
        print(f"Bytes: {response}")
        print(f"Hex: {response.hex(' ')}")

        # Try to parse as text first
        try:
            text_response = response.decode('ascii', errors='replace')
            print(f"\n=== Text Response ===")
            print(text_response)
            
            # Try to parse key-value pairs
            if 'KEY=' in text_response:
                print("\n=== Parsed Values ===")
                for line in text_response.split('\r\n'):
                    if not line.strip():
                        continue
                    
                    if '=' in line:
                        key, value = line.split('=', 1)
                        print(f"{key}: {value}")
                    else:
                        print(f"Line: {line}")
            
        except Exception as e:
            print(f"Error parsing as text: {e}")
        
        # Try to parse as binary formats
        try:
            # Format 7: Delphi TStringList.SaveToStream format
            # First byte is the number of lines
            if len(response) > 1 and response[0] in range(1, 10):  # Reasonable range for number of lines
                print("\n=== Binary Format 7 (TStringList) Parse Attempt ===")
                num_lines = response[0]
                print(f"Number of lines: {num_lines}")
                
                # Split rest of data by CRLF
                rest_data = response[1:]
                lines = rest_data.split(b'\r\n')
                
                print(f"Found {len(lines)} lines in data")
                for i, line in enumerate(lines):
                    if i >= num_lines:
                        break
                    if line:  # Skip empty lines
                        print(f"Line {i+1}: {line.decode('ascii', errors='replace')}")
                
                # Check for key-value pairs
                print("\n=== Format 7 Parsed Values ===")
                for i, line in enumerate(lines):
                    if i >= num_lines or not line:
                        continue
                    
                    line_text = line.decode('ascii', errors='replace')
                    if '=' in line_text:
                        key, value = line_text.split('=', 1)
                        print(f"{key}: {value}")
            
            # Format 8: Binary format with length-prefixed strings
            # First 4 bytes: number of strings
            # Then each string: 4 bytes length + string data
            elif len(response) > 8:  # At least 4 bytes for count + 4 bytes for first string length
                print("\n=== Binary Format 8 (Length-prefixed) Parse Attempt ===")
                try:
                    count = struct.unpack('<I', response[:4])[0]
                    if count > 20:  # Sanity check - shouldn't have too many strings
                        print(f"Count seems too large ({count}), probably not format 8")
                    else:
                        print(f"Number of strings: {count}")
                        
                        # Parse each string
                        offset = 4  # Start after the count
                        extracted_lines = []
                        
                        for i in range(count):
                            if offset + 4 <= len(response):
                                str_len = struct.unpack('<I', response[offset:offset+4])[0]
                                offset += 4
                                
                                if offset + str_len <= len(response):
                                    str_data = response[offset:offset+str_len]
                                    str_text = str_data.decode('ascii', errors='replace')
                                    print(f"String {i+1}: {str_text}")
                                    extracted_lines.append(str_text)
                                    offset += str_len
                                else:
                                    print(f"String {i+1}: Data too short for length {str_len}")
                                    break
                            else:
                                print(f"String {i+1}: Not enough data to read length")
                                break
                        
                        # Check for key-value pairs
                        print("\n=== Format 8 Parsed Values ===")
                        for line in extracted_lines:
                            if '=' in line:
                                key, value = line.split('=', 1)
                                print(f"{key}: {value}")
                except struct.error as e:
                    print(f"Error unpacking binary data: {e}")
        
        except Exception as e:
            print(f"Error parsing binary formats: {e}")
        
        # Close the connection
        sock.close()
        print("\nConnection closed")
        
    except Exception as e:
        print(f"Error: {e}")
        
if __name__ == "__main__":
    # Allow overriding host and port from command line
    if len(sys.argv) > 1:
        HOST = sys.argv[1]
    if len(sys.argv) > 2:
        PORT = int(sys.argv[2])
        
    main() 
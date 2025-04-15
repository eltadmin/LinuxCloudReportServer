#!/usr/bin/env python3
"""
Test client to debug the INIT response format
"""
import socket
import sys

def main():
    """Connect to the server and send an INIT command, then print the raw response"""
    host = '10.150.40.8'  # Or the server's IP address
    port = 8016  # The port used by the server
    
    try:
        # Create a socket
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            # Connect to the server
            s.connect((host, port))
            
            # Format the INIT command
            command = b'INIT ID=2 DT=250411 TM=142807 HST=DLUKAREV ATP=EBOCloudReportApp.exe AVR=6.0.0.0\n'
            
            # Send the command
            s.sendall(command)
            
            # Receive the response, up to 1024 bytes
            response = s.recv(1024)
            
            # Print the raw bytes received
            print(f"Raw response bytes: {response}")
            
            # Print as hex for debugging
            print(f"Hex response: {response.hex()}")
            
            # Try to decode and print the string representation
            try:
                decoded = response.decode('utf-8')
                print(f"Decoded response: {repr(decoded)}")
                
                # Print each line for clarity
                print("Response lines:")
                for i, line in enumerate(decoded.splitlines()):
                    print(f"Line {i+1}: {repr(line)}")
            except UnicodeDecodeError:
                print("Could not decode response as UTF-8")
    
    except ConnectionRefusedError:
        print(f"Connection to {host}:{port} refused. Is the server running?")
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    main() 
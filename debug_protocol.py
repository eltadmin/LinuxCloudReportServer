#!/usr/bin/env python3
"""
Protocol debugging tool for TCP server communication
"""
import socket
import sys
import binascii
import time

def send_init_command(host, port, client_id=5):
    """Send INIT command and analyze response in detail"""
    try:
        # Create socket
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.connect((host, port))
        print(f"Connected to {host}:{port}")
        
        # Create INIT command
        command = f"INIT ID={client_id} DT=250411 TM=143208 HST=DLUKAREV ATP=EBOCloudReportApp.exe AVR=6.0.0.0\n"
        
        # Send command
        print(f"Sending command: {command.strip()}")
        s.sendall(command.encode('ascii'))
        
        # Receive response with short timeout to get immediate response
        s.settimeout(2.0)
        try:
            data = s.recv(1024)
            
            # Analyze raw bytes
            print("\n=== RAW RESPONSE ANALYSIS ===")
            print(f"Length: {len(data)} bytes")
            print(f"Raw bytes: {data}")
            
            # Show hex dump for detailed inspection
            print("\n=== HEX DUMP ===")
            hex_dump = ' '.join([f'{b:02x}' for b in data])
            print(hex_dump)
            
            # Show ASCII representation
            print("\n=== ASCII ===")
            ascii_rep = ''.join([chr(b) if 32 <= b <= 126 else '.' for b in data])
            print(ascii_rep)
            
            # Try to decode as UTF-8
            print("\n=== UTF-8 DECODED ===")
            try:
                decoded = data.decode('utf-8')
                print(repr(decoded))
            except UnicodeDecodeError:
                print("Cannot decode as UTF-8")
            
            # Show each line with line endings visible
            print("\n=== LINES (with endings visible) ===")
            if b'\r\n' in data:
                lines = data.split(b'\r\n')
                for i, line in enumerate(lines):
                    line_repr = repr(line.decode('ascii', errors='replace'))
                    print(f"Line {i+1}: {line_repr}")
            else:
                lines = data.split(b'\n')
                for i, line in enumerate(lines):
                    line_repr = repr(line.decode('ascii', errors='replace'))
                    print(f"Line {i+1}: {line_repr}")
            
            # Try to parse Delphi TStrings.Values parameters
            print("\n=== PARAMETER EXTRACTION ===")
            
            # Function to extract value for a given key
            def extract_value(data_bytes, key):
                key_bytes = key.encode('ascii') + b'='
                lines = data_bytes.split(b'\r\n')
                
                for line in lines:
                    if line.startswith(key_bytes):
                        return line[len(key_bytes):].decode('ascii')
                return None
            
            # Extract LEN and KEY parameters
            len_value = extract_value(data, 'LEN')
            key_value = extract_value(data, 'KEY')
            
            print(f"LEN parameter: {len_value}")
            print(f"KEY parameter: {key_value}")
            
            if len_value is None:
                print("WARNING: LEN parameter not found - this would cause the client to fail")
            if key_value is None:
                print("WARNING: KEY parameter not found - this would cause the client to fail")
            
        except socket.timeout:
            print("Timeout waiting for response")
        finally:
            s.close()
    
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    # Use command line args if provided, otherwise use defaults
    host = sys.argv[1] if len(sys.argv) > 1 else '127.0.0.1'
    port = int(sys.argv[2]) if len(sys.argv) > 2 else 8016
    
    send_init_command(host, port) 
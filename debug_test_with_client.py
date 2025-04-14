#!/usr/bin/env python3
"""
Test script to simulate the original client's behavior without ATP and AVR parameters
"""
import socket
import time

def test_init_without_atp_avr(host='127.0.0.1', port=8016):
    """Simulate client sending INIT command without ATP and AVR parameters"""
    try:
        # Create socket
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.connect((host, port))
        print(f"Connected to {host}:{port}")
        
        # Create INIT command exactly as the original client sends it
        # Note: No ATP and AVR parameters
        init_cmd = "INIT ID=1 DT=250411 TM=143208 HST=CLOUD-REPORT-DE\n"
        
        print(f"Sending INIT command: {init_cmd.strip()}")
        s.sendall(init_cmd.encode('ascii'))
        
        # Receive response
        data = s.recv(1024)
        
        # Analyze raw bytes
        print("\n=== RAW RESPONSE ANALYSIS ===")
        print(f"Length: {len(data)} bytes")
        print(f"Raw bytes: {data}")
        print(f"Decoded: {data.decode('ascii')}")
        
        # Extract key info
        len_value = None
        key_value = None
        
        try:
            decoded = data.decode('ascii')
            lines = decoded.split('\r\n')
            
            for line in lines:
                if line.startswith('LEN='):
                    len_value = line[4:]
                elif line.startswith('KEY='):
                    key_value = line[4:]
            
            if len_value is None or key_value is None:
                print("ERROR: Could not extract LEN or KEY from response")
                return
                
            print(f"Extracted LEN={len_value}, KEY={key_value}")
            
            # Try a PING command
            print("\nSending PING command")
            s.sendall(b"PING\n")
            
            data = s.recv(1024)
            print(f"PING Response: {data.decode('ascii')}")
            
            # Try an ERRL command
            print("\nSending ERRL command")
            s.sendall(b"ERRL Test error message\n")
            
            data = s.recv(1024)
            print(f"ERRL Response: {data.decode('ascii')}")
            
            print("\nTest completed successfully!")
            
        except Exception as e:
            print(f"Error during communication: {e}")
            
    except Exception as e:
        print(f"Error: {e}")
    finally:
        s.close()

if __name__ == "__main__":
    import sys
    
    host = sys.argv[1] if len(sys.argv) > 1 else '127.0.0.1'
    port = int(sys.argv[2]) if len(sys.argv) > 2 else 8016
    
    test_init_without_atp_avr(host, port) 
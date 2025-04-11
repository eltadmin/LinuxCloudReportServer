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
                
            # Perform simulation of crypto key generation
            if len_value is not None and key_value is not None:
                print("\n=== CRYPTO KEY GENERATION SIMULATION ===")
                # Dictionary used for crypto key generation
                crypto_dictionary = [
                    '123hk12h8dcal',
                    'FT676Ugug6sFa',
                    'a6xbBa7A8a9la',
                    'qMnxbtyTFvcqi',
                    'cx7812vcxFRCC',
                    'bab7u682ftysv',
                    'YGbsux&Ygsyxg',
                    'MSN><hu8asG&&',
                    '23yY88syHXvvs',
                    '987sX&sysy891'
                ]
                
                key_len = int(len_value)
                server_key = key_value
                host_name = "DLUKAREV"  # Same as in the INIT command
                
                crypto_dict_part = crypto_dictionary[client_id - 1][:key_len]
                host_part = host_name[:2] + host_name[-1:]
                full_key = server_key + crypto_dict_part + host_part
                
                print(f"Dictionary entry (ID={client_id}): {crypto_dictionary[client_id - 1]}")
                print(f"Dictionary portion (len={key_len}): {crypto_dict_part}")
                print(f"Host portion: {host_part}")
                print(f"Full crypto key: {full_key}")
            
        except socket.timeout:
            print("Timeout waiting for response")
        finally:
            s.close()
    
    except Exception as e:
        print(f"Error: {e}")

def test_full_communication(host, port, client_id=5):
    """Test full client-server communication sequence"""
    try:
        # Create socket
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.connect((host, port))
        print(f"Connected to {host}:{port}")
        
        # Step 1: Send INIT command
        host_name = "DLUKAREV"
        init_cmd = f"INIT ID={client_id} DT=250411 TM=143208 HST={host_name} ATP=EBOCloudReportApp.exe AVR=6.0.0.0\n"
        print(f"\n[STEP 1] Sending INIT command: {init_cmd.strip()}")
        s.sendall(init_cmd.encode('ascii'))
        
        data = s.recv(1024)
        print(f"Response: {data}")
        
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
            
            # Generate crypto key
            crypto_dictionary = [
                '123hk12h8dcal',
                'FT676Ugug6sFa',
                'a6xbBa7A8a9la',
                'qMnxbtyTFvcqi',
                'cx7812vcxFRCC',
                'bab7u682ftysv',
                'YGbsux&Ygsyxg',
                'MSN><hu8asG&&',
                '23yY88syHXvvs',
                '987sX&sysy891'
            ]
            
            key_len = int(len_value)
            server_key = key_value
            
            crypto_dict_part = crypto_dictionary[client_id - 1][:key_len]
            host_part = host_name[:2] + host_name[-1:]
            full_key = server_key + crypto_dict_part + host_part
            
            print(f"Full crypto key: {full_key}")
            
            # Step 2: Send PING
            print("\n[STEP 2] Sending PING command")
            s.sendall(b"PING\n")
            
            data = s.recv(1024)
            ping_response = data.decode('ascii').strip()
            print(f"PING Response: {ping_response}")
            
            if ping_response != "PONG":
                print("ERROR: Unexpected PING response")
            
            # Step 3: Report a test error
            print("\n[STEP 3] Sending ERRL command")
            s.sendall(b"ERRL Test error message from debug client\n")
            
            data = s.recv(1024)
            errl_response = data.decode('ascii').strip()
            print(f"ERRL Response: {errl_response}")
            
            if errl_response != "OK":
                print("ERROR: Unexpected ERRL response")
            
            print("\nAll tests completed successfully!")
            
        except Exception as e:
            print(f"Error during communication: {e}")
        finally:
            s.close()
            
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    # Use command line args if provided, otherwise use defaults
    host = sys.argv[1] if len(sys.argv) > 1 else '127.0.0.1'
    port = int(sys.argv[2]) if len(sys.argv) > 2 else 8016
    
    # Choose test type
    if len(sys.argv) > 3 and sys.argv[3] == 'full':
        test_full_communication(host, port)
    else:
        send_init_command(host, port) 
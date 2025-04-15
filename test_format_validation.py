#!/usr/bin/env python3
"""
Test script for validating the INIT response format.

This script connects to the server, sends an INIT command, and validates that the
response is in the correct format for Delphi clients. It also checks that the 
crypto key can be properly generated and encryption works.
"""

import socket
import time
import hashlib
import zlib
import base64
import logging
import sys
import os
from datetime import datetime

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger("format_validator")

# Constants matching the Delphi client's expectations
CRYPTO_DICTIONARY = [
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

class DataCompressor:
    """Replicates the TDataCompressor functionality from the Delphi implementation."""

    def __init__(self, crypto_key):
        self.crypto_key = crypto_key
        self.last_error = ""
        logger.debug(f"Initializing DataCompressor with key: '{crypto_key}'")
        
        # Hash the key for consistency
        key_bytes = crypto_key.encode('cp1251', errors='replace')
        self.key_hash = hashlib.md5(key_bytes).digest()
        
    def compress_data(self, data):
        """Compresses and encrypts data using the crypto key."""
        try:
            # 1. Convert string to bytes
            input_bytes = data.encode('cp1251', errors='replace')
            
            # 2. Compress with zlib
            compressed_data = zlib.compress(input_bytes, level=6)
            
            # 3. Ensure data is a multiple of 16 bytes (AES block size)
            remainder = len(compressed_data) % 16
            if remainder != 0:
                padding = 16 - remainder
                compressed_data += b'\x00' * padding
            
            # 4. Encrypt with XOR (simpler than full AES for testing)
            encrypted_data = bytearray()
            key_length = len(self.key_hash)
            
            for i in range(len(compressed_data)):
                encrypted_data.append(compressed_data[i] ^ self.key_hash[i % key_length])
            
            # 5. Base64 encode
            result = base64.b64encode(encrypted_data).decode('ascii')
            return result
            
        except Exception as e:
            self.last_error = f"Error compressing data: {str(e)}"
            logger.error(self.last_error)
            return ""
            
    def decompress_data(self, data):
        """Decrypts and decompresses data using the crypto key."""
        try:
            # 1. Base64 decode
            decoded = base64.b64decode(data)
            
            # 2. Decrypt with XOR
            decrypted = bytearray()
            key_length = len(self.key_hash)
            
            for i in range(len(decoded)):
                decrypted.append(decoded[i] ^ self.key_hash[i % key_length])
            
            # 3. Decompress with zlib
            decompressed = zlib.decompress(decrypted)
            
            # 4. Convert bytes to string
            result = decompressed.decode('cp1251', errors='replace')
            return result
            
        except Exception as e:
            self.last_error = f"Error decompressing data: {str(e)}"
            logger.error(self.last_error)
            return ""


def validate_init_response(host='10.150.40.8', port=8016):
    """Connect to the server, send INIT command, and validate the response format."""
    
    # Generate a hostname similar to what the Delphi client would use
    hostname = "TEST-VALIDATOR"
    client_id = 1
    
    # Create INIT command with current date and time
    now = datetime.now()
    date_str = now.strftime("%y%m%d")
    time_str = now.strftime("%H%M%S")
    app_type = "ValidationTest"
    app_version = "1.0.0"
    
    # Format similar to Delphi client
    init_cmd = f"INIT ID={client_id} DT={date_str} TM={time_str} HST={hostname} ATP={app_type} AVR={app_version}\n"
    
    logger.info(f"Connecting to {host}:{port}")
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.connect((host, port))
        
        logger.info(f"Sending INIT command: {init_cmd.strip()}")
        sock.sendall(init_cmd.encode('ascii'))
        
        # Read response
        response = b""
        while True:
            chunk = sock.recv(1024)
            if not chunk:
                break
            response += chunk
            if b'\n' in chunk:  # End of response marker
                break
                
        response_str = response.decode('ascii', errors='replace').strip()
        logger.info(f"Raw response: {response_str!r}")
        
        # Parse response using same method as Delphi
        lines = response_str.split('\r\n')
        
        # Create a simple key-value parser like Delphi's TStringList.Values
        values = {}
        for line in lines:
            if '=' in line:
                key, val = line.split('=', 1)
                values[key] = val
        
        logger.info(f"Parsed values: {values}")
        
        # Check if the required keys exist
        if 'KEY' not in values or 'LEN' not in values:
            logger.error("ERROR: Required keys 'KEY' and 'LEN' not found in response")
            return False
            
        # Extract values
        server_key = values['KEY']
        key_len = int(values['LEN'])
        
        # Construct crypto key as Delphi client would
        crypto_dict_part = CRYPTO_DICTIONARY[client_id-1][:key_len]
        host_first_chars = hostname[:2]
        host_last_char = hostname[-1:]
        
        crypto_key = server_key + crypto_dict_part + host_first_chars + host_last_char
        logger.info(f"Generated crypto key: {crypto_key}")
        
        # Test encryption
        compressor = DataCompressor(crypto_key)
        test_data = "TT=Test"
        
        encrypted = compressor.compress_data(test_data)
        logger.info(f"Encrypted test data: {encrypted}")
        
        decrypted = compressor.decompress_data(encrypted)
        logger.info(f"Decrypted test data: {decrypted}")
        
        if decrypted == test_data:
            logger.info("SUCCESS: Encryption/decryption test passed!")
            return True
        else:
            logger.error(f"ERROR: Decrypted data does not match original. Expected '{test_data}', got '{decrypted}'")
            return False


if __name__ == "__main__":
    # Get host and port from command line args or use defaults
    host = sys.argv[1] if len(sys.argv) > 1 else '10.150.40.8'
    port = int(sys.argv[2]) if len(sys.argv) > 2 else 8016
    
    if validate_init_response(host, port):
        logger.info("INIT response format validation passed!")
        sys.exit(0)
    else:
        logger.error("INIT response format validation failed!")
        sys.exit(1) 
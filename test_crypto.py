#!/usr/bin/env python3
"""
Test script for crypto functions
This script can be used to test encryption/decryption with different client IDs
"""

import sys
import os

# Add the src directory to the Python path
current_dir = os.path.dirname(os.path.abspath(__file__))
src_dir = os.path.join(current_dir, 'src')
sys.path.append(src_dir)

import base64
import argparse
from src.crypto import DataCompressor, generate_client_crypto_key, check_registration_key
from src.constants import HARDCODED_KEYS, ID8_KEY, CRYPTO_DICTIONARY

# Define the length to use for ID=8 special case
ID8_LEN = 4

def test_client_id(client_id: int, server_key: str, host_name: str, data: str):
    """Test encryption/decryption with a specific client ID"""
    print(f"\n=== Testing client ID={client_id} ===")
    
    # Initialize compressor to handle the data
    compressor = DataCompressor()
    
    # Check if we have a hardcoded key for this client
    if client_id in HARDCODED_KEYS:
        key = HARDCODED_KEYS[client_id]
        print(f"Using hardcoded key: {key}")
    else:
        # Generate a key for this client ID
        key = generate_client_crypto_key(client_id, server_key, host_name)
        print(f"Generated key: {key}")
    
    # Now try to encrypt and decrypt some data
    print(f"Original data: {data}")
    
    try:
        # Encrypt the data
        encrypted = compressor.compress_data(data)
        print(f"Encrypted data: {encrypted[:30]}...")
        
        # Decrypt the data
        decrypted = compressor.decompress_data(encrypted)
        if isinstance(decrypted, bytes):
            decrypted = decrypted.decode()
        
        print(f"Decrypted data: {decrypted}")
        
        # Check if the decrypt matches the original
        if data == decrypted:
            print("✅ Encryption/decryption successful!")
        else:
            print("❌ Encryption/decryption failed! Data mismatch.")
    except Exception as e:
        print(f"Error during encryption/decryption: {str(e)}")
        
    print()

def test_compression():
    print("Testing data compression...")
    compressor = DataCompressor()
    test_data = b"This is a test string to compress and decompress" * 50
    
    compressed = compressor.compress_data(test_data)
    decompressed = compressor.decompress_data(compressed)
    
    print(f"Original size: {len(test_data)} bytes")
    print(f"Compressed size: {len(compressed)} bytes")
    print(f"Compression ratio: {len(compressed)/len(test_data):.2f}")
    print(f"Decompression successful: {test_data == decompressed}")
    print()

def test_registration_key(client_id):
    print(f"Testing registration key for client ID: {client_id}")
    
    # Test with some sample registration keys
    test_key = "ABCD-EFGH-IJKL-MNOP"
    try:
        # Convert client_id to string for check_registration_key
        result = check_registration_key(str(client_id), test_key)
        print(f"Check result for test key '{test_key}': {result}")
    except Exception as e:
        print(f"Error in check_registration_key: {str(e)}")
    
    # If client has a hardcoded key in constants, test it
    if client_id in HARDCODED_KEYS:
        hardcoded_key = HARDCODED_KEYS[client_id]
        try:
            result = check_registration_key(str(client_id), hardcoded_key)
            print(f"Check result for hardcoded key: {result}")
        except Exception as e:
            print(f"Error in check_registration_key: {str(e)}")
    else:
        print(f"No hardcoded key found for client ID {client_id}")
    print()

def test_key_generation(client_id):
    print(f"Testing key generation for client ID: {client_id}")
    
    try:
        # Get default server key and host name from command line arguments
        parser = argparse.ArgumentParser()
        parser.add_argument("--server-key", type=str, default="D5F2")
        parser.add_argument("--host-name", type=str, default="testhost")
        args, _ = parser.parse_known_args()
        
        crypto_key = generate_client_crypto_key(client_id, args.server_key, args.host_name)
        
        print("Generated key:")
        print(f"  {crypto_key}")
        
        print("Key generation successful")
    except Exception as e:
        print(f"Error generating keys: {str(e)}")
    print()

def main():
    """Main function"""
    parser = argparse.ArgumentParser(description="Test crypto functionality for Cloud Report Server")
    parser.add_argument("--client-id", type=int, default=1, help="Client ID to use for testing")
    parser.add_argument("--test-compression", action="store_true", help="Test data compression")
    parser.add_argument("--test-reg-key", action="store_true", help="Test registration key validation")
    parser.add_argument("--test-key-gen", action="store_true", help="Test crypto key generation")
    parser.add_argument("--test-encrypt", action="store_true", help="Test encryption/decryption")
    parser.add_argument("--server-key", type=str, default="D5F2", help="Server key for encryption test")
    parser.add_argument("--host-name", type=str, default="testhost", help="Host name for encryption test")
    parser.add_argument("--data", type=str, default="TT=Test\r\nID=1\r\nFN=TestFirm\r\nHS=testhost", 
                        help="Data to encrypt/decrypt")
    parser.add_argument("--test-all", action="store_true", help="Run all tests")
    
    args = parser.parse_args()
    
    if args.test_all or args.test_compression:
        test_compression()
    
    if args.test_all or args.test_reg_key:
        test_registration_key(args.client_id)
    
    if args.test_all or args.test_key_gen:
        test_key_generation(args.client_id)
        
    if args.test_all or args.test_encrypt:
        test_client_id(args.client_id, args.server_key, args.host_name, args.data)
    
    if not (args.test_compression or args.test_reg_key or args.test_key_gen or args.test_encrypt or args.test_all):
        parser.print_help()

if __name__ == "__main__":
    main() 
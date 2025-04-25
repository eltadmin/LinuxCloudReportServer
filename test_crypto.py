#!/usr/bin/env python3
"""
Test script for crypto functions
This script can be used to test encryption/decryption with different client IDs
"""

import sys
import os
import base64
import argparse
from src.crypto import DataCompressor, generate_client_crypto_key
from src.constants import HARDCODED_KEYS, ID8_KEY, CRYPTO_DICTIONARY

def test_client_id(client_id: int, server_key: str, host_name: str, data: str):
    """Test encryption/decryption for a specific client ID"""
    print(f"\n=== Testing client ID={client_id} ===")
    
    # Generate crypto key
    crypto_key = ""
    
    # Check if hardcoded key exists
    if client_id in HARDCODED_KEYS:
        crypto_key = HARDCODED_KEYS[client_id]
        print(f"Using hardcoded key: {crypto_key}")
    elif client_id == 8:
        crypto_key = f"{ID8_KEY}{CRYPTO_DICTIONARY[client_id-1][:ID8_LEN]}{host_name[:2]}{host_name[-1]}"
        print(f"Using special key for ID=8: {crypto_key}")
    else:
        # Generate normal key
        crypto_key = generate_client_crypto_key(client_id, server_key, host_name)
        print(f"Generated key: {crypto_key}")
    
    # Create compressor with the crypto key
    compressor = DataCompressor(crypto_key, client_id)
    
    # Encrypt data
    print(f"Original data: {data}")
    encrypted = compressor.compress_data(data)
    print(f"Encrypted data: {encrypted}")
    
    # Decrypt data
    decrypted = compressor.decompress_data(encrypted)
    print(f"Decrypted data: {decrypted}")
    
    # Check if decryption was successful
    if data == decrypted:
        print("✅ Test PASSED: Data decrypted successfully")
    else:
        print("❌ Test FAILED: Data decryption mismatch")
        
    return encrypted, decrypted
    
def main():
    """Main function"""
    parser = argparse.ArgumentParser(description='Test crypto functions with different client IDs')
    parser.add_argument('--client-id', type=int, default=9, help='Client ID to test (default: 9)')
    parser.add_argument('--server-key', type=str, default='D5F2', help='Server key (default: D5F2)')
    parser.add_argument('--host-name', type=str, default='testhost', help='Host name (default: testhost)')
    parser.add_argument('--data', type=str, default='TT=Test\r\nID=9\r\nFN=TestFirm\r\nHS=testhost', 
                        help='Data to encrypt/decrypt')
    parser.add_argument('--test-all', action='store_true', help='Test all client IDs (1-10)')
    
    args = parser.parse_args()
    
    print("=== Crypto Test Tool ===")
    print(f"Server key: {args.server_key}")
    print(f"Host name: {args.host_name}")
    
    if args.test_all:
        for client_id in range(1, 11):
            test_client_id(client_id, args.server_key, args.host_name, args.data)
    else:
        test_client_id(args.client_id, args.server_key, args.host_name, args.data)
    
    print("\nAll tests completed!")

if __name__ == "__main__":
    main() 
#!/usr/bin/env python3
"""
Test script to verify imports are working correctly
"""

import sys
import os

# Add the current directory to the Python path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

try:
    print("Testing imports...")
    from src.constants import (
        TCP_ERR_INVALID_CRYPTO_KEY,
        TCP_ERR_INVALID_DATA_PACKET,
        TCP_ERR_FAIL_DECODE_DATA,
        TCP_ERR_FAIL_ENCODE_DATA,
        TCP_ERR_FAIL_INIT_CLIENT_ID,
        TCP_ERR_CHECK_UPDATE_ERROR,
    )
    print("Constants import successful!")
    
    from src.connection import TCPCommandHandler
    print("TCPCommandHandler import successful!")
    
    # Test the error codes
    print(f"TCP_ERR_INVALID_CRYPTO_KEY = {TCP_ERR_INVALID_CRYPTO_KEY}")
    print(f"TCP_ERR_INVALID_DATA_PACKET = {TCP_ERR_INVALID_DATA_PACKET}")
    print(f"TCP_ERR_FAIL_DECODE_DATA = {TCP_ERR_FAIL_DECODE_DATA}")
    print(f"TCP_ERR_FAIL_ENCODE_DATA = {TCP_ERR_FAIL_ENCODE_DATA}")
    print(f"TCP_ERR_FAIL_INIT_CLIENT_ID = {TCP_ERR_FAIL_INIT_CLIENT_ID}")
    print(f"TCP_ERR_CHECK_UPDATE_ERROR = {TCP_ERR_CHECK_UPDATE_ERROR}")
    
    print("All imports successful!")
except Exception as e:
    print(f"Error during import test: {e}")
    print(f"Python path: {sys.path}") 
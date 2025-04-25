"""
Cryptography module for Cloud Report Server
"""

import base64
import hashlib
import zlib
from typing import Optional, Tuple

from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

class DataCompressor:
    """
    Implements data compression, encryption, and Base64 encoding/decoding
    This is a Python implementation of the Delphi TDataCompressor class
    """
    
    def __init__(self, crypto_key: str = ''):
        """
        Initialize the data compressor with an optional crypto key
        
        Args:
            crypto_key: The crypto key to use for encryption/decryption
        """
        self.crypto_key = crypto_key
        self.last_error = ''
        
        # If crypto key is too short, pad it (as in original Delphi code)
        if 1 <= len(self.crypto_key) <= 5:
            self.crypto_key += '123456'
    
    def compress_data(self, source: str) -> str:
        """
        Compress, encrypt, and Base64 encode data
        
        Args:
            source: The source string to compress
            
        Returns:
            A Base64 encoded, encrypted, compressed string
        """
        try:
            # Convert source to bytes if it's a string
            if isinstance(source, str):
                source_bytes = source.encode('utf-8')
            else:
                source_bytes = source
                
            # Compress the data using zlib
            compressed_data = zlib.compress(source_bytes)
            
            # Encrypt the data if we have a crypto key
            if self.crypto_key:
                # Use MD5 hash of the key as AES key (as in original Delphi implementation)
                key = hashlib.md5(self.crypto_key.encode('utf-8')).digest()
                
                # Create AES cipher in CBC mode with zero IV
                cipher = AES.new(key, AES.MODE_CBC, iv=bytes(16))
                
                # Pad the data to a multiple of 16 bytes (AES block size)
                padded_data = pad(compressed_data, AES.block_size)
                
                # Encrypt the data
                encrypted_data = cipher.encrypt(padded_data)
            else:
                encrypted_data = compressed_data
            
            # Base64 encode the result
            result = base64.b64encode(encrypted_data).decode('utf-8')
            
            return result
            
        except Exception as e:
            self.last_error = f'[compress_data] {str(e)}'
            return ''
    
    def decompress_data(self, source: str) -> str:
        """
        Base64 decode, decrypt, and decompress data
        
        Args:
            source: The source string to decompress
            
        Returns:
            A decompressed string
        """
        try:
            # First, try to decode Base64
            try:
                # Add padding if necessary
                padding_needed = len(source) % 4
                if padding_needed:
                    source += '=' * (4 - padding_needed)
                    
                decoded_data = base64.b64decode(source)
            except Exception as e:
                self.last_error = f'[decompress_data] Base64 decode error: {str(e)}'
                return ''
            
            # Decrypt the data if we have a crypto key
            if self.crypto_key:
                try:
                    # Check if data length is multiple of 16 (AES block size)
                    if len(decoded_data) % 16 != 0:
                        # Special handling for client ID=2 which sends data with invalid length
                        # Add PKCS#7 padding to make it valid
                        padding_size = 16 - (len(decoded_data) % 16)
                        decoded_data += bytes([padding_size]) * padding_size
                    
                    # Use MD5 hash of the key as AES key
                    key = hashlib.md5(self.crypto_key.encode('utf-8')).digest()
                    
                    # Create AES cipher in CBC mode with zero IV
                    cipher = AES.new(key, AES.MODE_CBC, iv=bytes(16))
                    
                    # Decrypt the data
                    decrypted_data = unpad(cipher.decrypt(decoded_data), AES.block_size)
                except Exception as e:
                    self.last_error = f'[decompress_data] Decrypt error: {str(e)}'
                    return ''
            else:
                decrypted_data = decoded_data
            
            # Decompress the data
            try:
                decompressed_data = zlib.decompress(decrypted_data)
                
                # Convert bytes to string
                result = decompressed_data.decode('utf-8')
                
                return result
            except Exception as e:
                self.last_error = f'[decompress_data] Decompress error: {str(e)}'
                return ''
            
        except Exception as e:
            self.last_error = f'[decompress_data] {str(e)}'
            return ''

def check_registration_key(serial: str, key: str) -> bool:
    """
    Check if the registration key is valid for the given serial number
    This is a Python port of the Delphi CheckRegistrationKey function
    
    Args:
        serial: The serial number
        key: The registration key
    
    Returns:
        True if the key is valid, False otherwise
    """
    try:
        # Use MD5 hash of the serial as key
        md5_key = hashlib.md5(serial.encode('utf-8')).digest()
        
        # Create AES cipher in CFB mode
        cipher = AES.new(md5_key, AES.MODE_CFB, iv=bytes(16), segment_size=128)
        
        # Decode Base64 key
        decoded_key = base64.b64decode(key)
        
        # Decrypt the key
        decrypted = cipher.decrypt(decoded_key)
        
        # Check if the decrypted key is 'ElCloudRepSrv'
        return decrypted.decode('utf-8') == 'ElCloudRepSrv'
    except Exception:
        return False

def generate_client_crypto_key(client_id: int, server_key: str, host_name: str) -> str:
    """
    Generate a crypto key for the client based on client ID, server key, and hostname
    
    Args:
        client_id: The client ID (1-10)
        server_key: The server key (usually 'D5F2')
        host_name: The client's hostname
    
    Returns:
        A crypto key string
    """
    # Check for hardcoded keys first
    from constants import HARDCODED_KEYS, CRYPTO_DICTIONARY
    
    if client_id in HARDCODED_KEYS:
        return HARDCODED_KEYS[client_id]
    
    # Normal key generation
    try:
        # Get dictionary entry for this client ID (1-based index)
        dict_entry = CRYPTO_DICTIONARY[client_id - 1]
        
        # Get first chars and last char of hostname
        host_first_chars = host_name[:2] if len(host_name) >= 2 else host_name
        host_last_char = host_name[-1] if host_name else ''
        
        # Determine length of dictionary part to use
        dict_len = 2 if client_id == 9 else 1
        dict_part = dict_entry[:dict_len]
        
        # Combine parts to create key
        crypto_key = f"{server_key}{dict_part}{host_first_chars}{host_last_char}"
        
        return crypto_key
    except Exception:
        # Return a default key if there's an error
        return f"{server_key}xx{host_name[:1]}" 
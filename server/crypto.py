import zlib
import base64
import logging
import hashlib
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

logger = logging.getLogger(__name__)

class DataCompressor:
    """
    Replicates the TDataCompressor functionality from the Delphi implementation.
    Used for encrypting/decrypting data between client and server using AES (Rijndael) encryption.
    """
    def __init__(self, crypto_key):
        self.crypto_key = crypto_key
        self.last_error = ""
        logger.debug(f"Initializing DataCompressor with key: '{crypto_key}'")
        
        # Save the original key for debugging
        self.original_key = self.crypto_key
            
        # Hash the key to get a consistent length for AES (exactly matching Delphi's DCP implementation)
        try:
            # Use latin1 encoding which is most compatible with Delphi's Windows-1251
            key_bytes = self.crypto_key.encode('latin1')
            key_hash = hashlib.md5(key_bytes).digest()
            self.aes_key = key_hash
            logger.debug(f"Prepared AES key (MD5 hash): {key_hash.hex()}")
        except Exception as e:
            logger.error(f"Error preparing key: {e}")
            # Fallback - direct MD5 of string representation if all else fails
            self.aes_key = hashlib.md5(str(self.crypto_key).encode('utf-8', errors='replace')).digest()
            logger.warning(f"Using fallback key generation: {self.aes_key.hex()}")
        
        # Create a fixed IV of zeros to match Delphi's behavior
        self.iv = b'\x00' * 16
        logger.debug(f"Using fixed IV: {self.iv.hex()}")

    def compress_data(self, data):
        """Compresses and encrypts data using the crypto key - matches Delphi implementation"""
        try:
            logger.debug(f"Starting compress_data with key '{self.original_key}', MD5 hash: {self.aes_key.hex()}")
            logger.debug(f"Input data (length: {len(data)}): '{data[:100]}...' (truncated if long)")
            
            # 1. Convert string to bytes using latin1 encoding (compatible with Delphi)
            try:
                input_bytes = data.encode('latin1')
                logger.debug(f"Input bytes ({len(input_bytes)} bytes): {input_bytes[:30].hex()}...")
            except Exception as e:
                logger.error(f"Error converting string to bytes: {e}")
                # Fallback to UTF-8 with replacement
                input_bytes = data.encode('utf-8', errors='replace')
                logger.debug(f"Fallback encoding: {input_bytes[:30].hex()}...")
            
            # 2. Compress data with zlib at best compression level
            try:
                compressed_data = zlib.compress(input_bytes, level=zlib.Z_BEST_COMPRESSION)
                logger.debug(f"Compressed data ({len(compressed_data)} bytes): {compressed_data[:30].hex()}...")
            except Exception as e:
                logger.error(f"Error compressing data: {e}")
                raise
            
            # 3. Encrypt with Rijndael (AES) using PKCS#7 padding
            try:
                cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
                padded_data = pad(compressed_data, AES.block_size, style='pkcs7')
                encrypted_data = cipher.encrypt(padded_data)
                logger.debug(f"Encrypted data ({len(encrypted_data)} bytes): {encrypted_data[:30].hex()}...")
            except Exception as e:
                logger.error(f"Error encrypting data: {e}")
                raise
            
            # 4. Base64 encode WITHOUT padding (= signs) - to match Delphi behavior
            try:
                result = base64.b64encode(encrypted_data).decode('ascii').rstrip('=')
                logger.debug(f"Base64 result (length: {len(result)}): '{result[:60]}...'")
            except Exception as e:
                logger.error(f"Error base64 encoding: {e}")
                raise
            
            return result
            
        except Exception as e:
            self.last_error = f"Error compressing data: {str(e)}"
            logger.error(self.last_error)
            logger.error(f"Compression failed: {e}", exc_info=True)
            return ""
            
    def decompress_data(self, data):
        """Decrypts and decompresses data using the crypto key - matches Delphi implementation"""
        try:
            logger.debug(f"Starting decompress_data with key '{self.original_key}', MD5 hash: {self.aes_key.hex()}")
            logger.debug(f"Input data (length: {len(data)}): '{data[:60]}...'")
            
            # 1. Add padding to Base64 if needed
            padding_needed = len(data) % 4
            if padding_needed:
                padded_data = data + ('=' * (4 - padding_needed))
                logger.debug(f"Added {4 - padding_needed} padding characters to Base64 data")
            else:
                padded_data = data
            
            # 2. Base64 decode
            try:
                decoded = base64.b64decode(padded_data)
                logger.debug(f"Base64 decoded ({len(decoded)} bytes): {decoded[:30].hex()}...")
            except Exception as e:
                logger.error(f"Base64 decoding failed: {e}")
                raise
            
            # 3. Decrypt with AES using fixed IV
            try:
                cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
                decrypted_padded = cipher.decrypt(decoded)
                # Unpad using PKCS#7
                decrypted = unpad(decrypted_padded, AES.block_size, style='pkcs7')
                logger.debug(f"Decrypted data ({len(decrypted)} bytes): {decrypted[:30].hex()}...")
            except Exception as e:
                logger.error(f"AES decryption failed: {e}")
                raise
            
            # 4. Decompress with zlib
            try:
                decompressed = zlib.decompress(decrypted)
                logger.debug(f"Decompressed data ({len(decompressed)} bytes): {decompressed[:30].hex()}...")
            except Exception as e:
                logger.error(f"Decompression failed: {e}")
                raise
            
            # 5. Decode bytes to string using latin1 (compatible with Delphi)
            try:
                result = decompressed.decode('latin1')
                logger.debug(f"Decoded result (length: {len(result)}): '{result[:60]}...'")
            except Exception as e:
                logger.error(f"Character decoding failed with latin1: {e}")
                # Fallback to UTF-8 with replacement
                result = decompressed.decode('utf-8', errors='replace')
                logger.debug(f"Fallback UTF-8 decoding used")
            
            return result
            
        except Exception as e:
            self.last_error = f"Error decompressing data: {str(e)}"
            logger.error(self.last_error)
            logger.error(f"Decompression failed: {e}", exc_info=True)
            return ""

def generate_crypto_key(server_key, dict_entry_part, host_first_chars, host_last_char):
    """
    Generate the crypto key according to the specified algorithm.
    
    Args:
        server_key: The server key (typically "D5F2")
        dict_entry_part: Part of the dictionary entry for the client ID
        host_first_chars: First 2 characters of the host IP
        host_last_char: Last character of the host IP
        
    Returns:
        The generated crypto key
    """
    crypto_key = f"{server_key}{dict_entry_part}{host_first_chars}{host_last_char}"
    logger.debug(f"Generated crypto key: {crypto_key}")
    return crypto_key 
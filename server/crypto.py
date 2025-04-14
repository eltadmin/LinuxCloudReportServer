import zlib
import base64
import logging
import hashlib
import math
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
        
        # Prepare the key for AES encryption (must be 16, 24, or 32 bytes)
        if len(crypto_key) < 6:
            padded_key = crypto_key + '123456'
            logger.debug(f"Key too short, padded to: '{padded_key}'")
            self.crypto_key = padded_key
            
        # Hash the key to get a consistent length for AES (exactly matching Delphi's DCP implementation)
        key_hash = hashlib.md5(self.crypto_key.encode('latin1')).digest()
        self.aes_key = key_hash
        logger.debug(f"Prepared AES key (MD5 hash): {key_hash.hex()}")
        
        # Create a fixed IV of zeros to match Delphi's behavior
        self.iv = b'\x00' * 16
        logger.debug(f"Using fixed IV: {self.iv.hex()}")
        
    def _ensure_block_size(self, data, block_size=16):
        """Ensure data is a multiple of block_size by padding with zeros if needed"""
        if len(data) % block_size == 0:
            return data
        
        padding_needed = block_size - (len(data) % block_size)
        return data + (b'\x00' * padding_needed)
        
    def compress_data(self, data):
        """Compresses and encrypts data using the crypto key - matches Delphi implementation"""
        try:
            logger.debug(f"Compressing data (length: {len(data)})")
            
            # 1. Convert string to bytes using Latin-1 (similar to Delphi's string handling)
            # Use latin1 encoding to match Delphi's ANSI string representation
            input_bytes = data.encode('latin1')
            logger.debug(f"Input bytes (latin1): {len(input_bytes)} bytes")
            
            # 2. Compress data with zlib at level 6 (Delphi default)
            compressed_data = zlib.compress(input_bytes, level=6)
            logger.debug(f"Compressed size: {len(compressed_data)} bytes")
            
            # 3. Ensure data is a multiple of the block size (similar to Delphi's StringStream behavior)
            padded_data = self._ensure_block_size(compressed_data)
            logger.debug(f"Block aligned size: {len(padded_data)} bytes")
            
            # 4. Encrypt with Rijndael (AES) using fixed IV
            cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
            encrypted_data = cipher.encrypt(padded_data)
            logger.debug(f"Encrypted size: {len(encrypted_data)} bytes")
            
            # 5. Base64 encode WITHOUT including IV - to match Delphi behavior
            result = base64.b64encode(encrypted_data).decode('ascii')
            logger.debug(f"Base64 encoded result (length: {len(result)})")
            
            return result
            
        except Exception as e:
            self.last_error = f"Error compressing data: {str(e)}"
            logger.error(self.last_error)
            logger.error(f"Compression failed: {e}", exc_info=True)
            return ""
            
    def decompress_data(self, data):
        """Decrypts and decompresses data using the crypto key - matches Delphi implementation"""
        try:
            logger.debug(f"Decompressing data (length: {len(data)})")
            
            # 1. Base64 decode
            decoded = base64.b64decode(data)
            logger.debug(f"Base64 decoded size: {len(decoded)} bytes")
            
            # 2. Decrypt with Rijndael (AES) using fixed IV
            cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
            decrypted = cipher.decrypt(decoded)
            logger.debug(f"Decrypted size: {len(decrypted)} bytes")
            
            # 3. Decompress with zlib - let zlib handle trailing zeros
            try:
                decompressed = zlib.decompress(decrypted)
                logger.debug(f"Decompressed size: {len(decompressed)} bytes")
            except zlib.error as e:
                # Try to find the actual compressed data by checking for zlib header
                # (similar to how Delphi StringStream might behave)
                zlib_header = b'\x78'  # Most zlib streams start with 0x78
                if zlib_header in decrypted[:4]:
                    pos = decrypted.find(zlib_header)
                    if pos > -1:
                        logger.debug(f"Found zlib header at position {pos}")
                        decrypted = decrypted[pos:]
                        try:
                            decompressed = zlib.decompress(decrypted)
                            logger.debug(f"Decompressed after header fix: {len(decompressed)} bytes")
                        except:
                            logger.error("Failed to decompress even after finding zlib header")
                            raise
                    else:
                        raise
                else:
                    # Try removing trailing zeros (Delphi might add zeros for block alignment)
                    try:
                        # Remove trailing zeros
                        clean_data = decrypted.rstrip(b'\x00')
                        logger.debug(f"Cleaned data size: {len(clean_data)} bytes")
                        decompressed = zlib.decompress(clean_data)
                        logger.debug(f"Decompressed after cleaning: {len(decompressed)} bytes")
                    except:
                        logger.error("Failed to decompress even after cleaning")
                        raise
            
            # 4. Convert back to string using Latin-1 (similar to Delphi's string handling)
            result = decompressed.decode('latin1', errors='replace')
            logger.debug(f"Decoded result (length: {len(result)})")
            
            return result
            
        except Exception as e:
            self.last_error = f"Error decompressing data: {str(e)}"
            logger.error(self.last_error)
            logger.error(f"Decompression failed: {e}", exc_info=True)
            return "" 
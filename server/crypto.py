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
        
        # Prepare the key for AES encryption (must be 16, 24, or 32 bytes)
        if len(crypto_key) < 6:
            padded_key = crypto_key + '123456'
            logger.debug(f"Key too short, padded to: '{padded_key}'")
            self.crypto_key = padded_key
            
        # Hash the key to get a consistent length for AES
        key_hash = hashlib.md5(self.crypto_key.encode()).digest()
        self.aes_key = key_hash
        logger.debug(f"Prepared AES key (MD5 hash): {key_hash.hex()}")
        
        # Create a fixed IV of zeros to match Delphi's behavior
        self.iv = b'\x00' * 16
        logger.debug(f"Using fixed IV: {self.iv.hex()}")
        
    def compress_data(self, data):
        """Compresses and encrypts data using the crypto key - matches Delphi implementation"""
        try:
            logger.debug(f"Compressing data (length: {len(data)})")
            
            # 1. Compress data with zlib
            compressed_data = zlib.compress(data.encode('utf-8'))
            logger.debug(f"Compressed size: {len(compressed_data)} bytes")
            
            # 2. Encrypt with Rijndael (AES) using fixed IV
            cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
            padded_data = pad(compressed_data, AES.block_size)
            logger.debug(f"Padded size: {len(padded_data)} bytes")
            
            encrypted_data = cipher.encrypt(padded_data)
            logger.debug(f"Encrypted size: {len(encrypted_data)} bytes")
            
            # 3. Base64 encode WITHOUT including IV - to match Delphi behavior
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
            decrypted_padded = cipher.decrypt(decoded)
            logger.debug(f"Decrypted padded size: {len(decrypted_padded)} bytes")
            
            # 3. Remove padding
            try:
                decrypted = unpad(decrypted_padded, AES.block_size)
                logger.debug(f"Unpadded size: {len(decrypted)} bytes")
            except ValueError as e:
                logger.warning(f"Unpadding failed: {e}. Using padded data.")
                decrypted = decrypted_padded
            
            # 4. Decompress with zlib
            decompressed = zlib.decompress(decrypted)
            logger.debug(f"Decompressed size: {len(decompressed)} bytes")
            
            result = decompressed.decode('utf-8')
            logger.debug(f"Decoded result (length: {len(result)})")
            
            return result
            
        except Exception as e:
            self.last_error = f"Error decompressing data: {str(e)}"
            logger.error(self.last_error)
            logger.error(f"Decompression failed: {e}", exc_info=True)
            return "" 
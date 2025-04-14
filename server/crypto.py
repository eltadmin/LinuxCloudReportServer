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
        # Prepare the key for AES encryption (must be 16, 24, or 32 bytes)
        self.aes_key = self._prepare_key(crypto_key)
        
    def _prepare_key(self, key):
        """Prepare the key for AES encryption (must be 16, 24, or 32 bytes)"""
        if len(key) < 6:
            key = key + '123456'  # Match Delphi implementation for short keys
            
        # Hash the key to get a consistent length for AES
        key_hash = hashlib.md5(key.encode()).digest()
        return key_hash
        
    def compress_data(self, data):
        """Compresses and encrypts data using the crypto key - matches Delphi implementation"""
        try:
            # 1. Compress data with zlib
            compressed_data = zlib.compress(data.encode('utf-8'))
            
            # 2. Encrypt with Rijndael (AES)
            cipher = AES.new(self.aes_key, AES.MODE_CBC)
            iv = cipher.iv
            padded_data = pad(compressed_data, AES.block_size)
            encrypted_data = cipher.encrypt(padded_data)
            
            # 3. Base64 encode (include IV at the beginning)
            result = base64.b64encode(iv + encrypted_data).decode('ascii')
            
            return result
            
        except Exception as e:
            self.last_error = f"Error compressing data: {str(e)}"
            logger.error(self.last_error)
            return ""
            
    def decompress_data(self, data):
        """Decrypts and decompresses data using the crypto key - matches Delphi implementation"""
        try:
            # 1. Base64 decode
            decoded = base64.b64decode(data)
            
            # 2. Extract IV and encrypted data
            iv = decoded[:16]
            encrypted_data = decoded[16:]
            
            # 3. Decrypt with Rijndael (AES)
            cipher = AES.new(self.aes_key, AES.MODE_CBC, iv)
            decrypted_padded = cipher.decrypt(encrypted_data)
            decrypted = unpad(decrypted_padded, AES.block_size)
            
            # 4. Decompress with zlib
            decompressed = zlib.decompress(decrypted)
            
            return decompressed.decode('utf-8')
            
        except Exception as e:
            self.last_error = f"Error decompressing data: {str(e)}"
            logger.error(self.last_error)
            return "" 
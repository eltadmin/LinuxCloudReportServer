import zlib
import base64
import logging
import hashlib

logger = logging.getLogger(__name__)

class DataCompressor:
    """
    Replicates the TDataCompressor functionality from the Delphi implementation.
    Used for encrypting/decrypting data between client and server.
    """
    def __init__(self, crypto_key):
        self.crypto_key = crypto_key
        self.last_error = ""
        
    def compress_data(self, data):
        """Compresses and encrypts data using the crypto key"""
        try:
            # First compress the data using zlib
            compressed_data = zlib.compress(data.encode('utf-8'))
            
            # Then encode to base64 for safe transmission
            encoded_data = base64.b64encode(compressed_data).decode('utf-8')
            
            # Apply simple XOR encoding with MD5 hash of the crypto key
            key_hash = hashlib.md5(self.crypto_key.encode('utf-8')).digest()
            result = []
            
            for i, char in enumerate(encoded_data):
                key_char = key_hash[i % len(key_hash)]
                # XOR the character code with the key byte
                xored = chr(ord(char) ^ key_char)
                result.append(xored)
                
            return ''.join(result)
        except Exception as e:
            self.last_error = f"Error compressing data: {str(e)}"
            logger.error(self.last_error)
            return ""
            
    def decompress_data(self, data):
        """Decrypts and decompresses data using the crypto key"""
        try:
            # First decrypt with XOR and crypto key hash
            key_hash = hashlib.md5(self.crypto_key.encode('utf-8')).digest()
            decrypted = []
            
            for i, char in enumerate(data):
                key_char = key_hash[i % len(key_hash)]
                # XOR the character code with the key byte to get original
                xored = chr(ord(char) ^ key_char)
                decrypted.append(xored)
                
            # Decode base64
            decoded = base64.b64decode(''.join(decrypted))
            
            # Decompress zlib
            decompressed = zlib.decompress(decoded)
            
            return decompressed.decode('utf-8')
        except Exception as e:
            self.last_error = f"Error decompressing data: {str(e)}"
            logger.error(self.last_error)
            return "" 
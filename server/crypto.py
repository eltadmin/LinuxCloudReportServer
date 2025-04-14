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
        
        # Save the original key for debugging
        self.original_key = self.crypto_key
            
        # Hash the key to get a consistent length for AES (exactly matching Delphi's DCP implementation)
        # Use cp1251 encoding which is most compatible with Delphi's Windows-1251 for Cyrillic
        try:
            encodings = ['cp1251', 'latin1', 'utf-8']
            key_bytes = None
            
            for encoding in encodings:
                try:
                    key_bytes = self.crypto_key.encode(encoding)
                    logger.debug(f"Key encoded successfully with {encoding}")
                    break
                except UnicodeEncodeError:
                    continue
            
            if key_bytes is None:
                logger.warning("All encodings failed for key, using utf-8 with 'replace'")
                key_bytes = self.crypto_key.encode('utf-8', errors='replace')
                
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
        
    def _ensure_block_size(self, data, block_size=16):
        """Ensure data is a multiple of block_size by padding with zeros if needed"""
        if len(data) % block_size == 0:
            return data
        
        padding_needed = block_size - (len(data) % block_size)
        return data + (b'\x00' * padding_needed)
        
    def _try_multiple_encodings(self, text):
        """Try multiple encodings to convert text to bytes, starting with the most compatible with Delphi"""
        encodings = ['cp1251', 'latin1', 'utf-8', 'cp866']
        
        for encoding in encodings:
            try:
                result = text.encode(encoding)
                logger.debug(f"Successfully encoded using {encoding}")
                return result
            except UnicodeEncodeError:
                logger.debug(f"Failed to encode with {encoding}")
                continue
                
        # If all encodings fail, use utf-8 with replace option
        logger.warning("All encodings failed, using utf-8 with 'replace'")
        return text.encode('utf-8', errors='replace')
        
    def _try_decode_multiple_encodings(self, data):
        """Try multiple decodings to convert bytes to text, starting with the most compatible with Delphi"""
        encodings = ['cp1251', 'latin1', 'utf-8', 'cp866']
        
        for encoding in encodings:
            try:
                result = data.decode(encoding)
                logger.debug(f"Successfully decoded using {encoding}")
                return result
            except UnicodeDecodeError:
                logger.debug(f"Failed to decode with {encoding}")
                continue
                
        # If all decodings fail, use utf-8 with replace option
        logger.warning("All decodings failed, using utf-8 with 'replace'")
        return data.decode('utf-8', errors='replace')

    def compress_data(self, data):
        """Compresses and encrypts data using the crypto key - matches Delphi implementation"""
        try:
            logger.debug(f"Starting compress_data with key '{self.original_key}', MD5 hash: {self.aes_key.hex()}")
            logger.debug(f"Input data (length: {len(data)}): '{data}'")
            
            # 1. Convert string to bytes using appropriate encoding for Cyrillic
            input_bytes = self._try_multiple_encodings(data)
            logger.debug(f"Input bytes ({len(input_bytes)} bytes): {input_bytes.hex()[:60]}...")
            
            # 2. Compress data with zlib at level 6 (Delphi default)
            compressed_data = zlib.compress(input_bytes, level=6)
            logger.debug(f"Compressed data ({len(compressed_data)} bytes): {compressed_data.hex()[:60]}...")
            
            # 3. Ensure data is a multiple of the block size (similar to Delphi's StringStream behavior)
            padded_data = self._ensure_block_size(compressed_data)
            padding_added = len(padded_data) - len(compressed_data)
            logger.debug(f"Padded data ({len(padded_data)} bytes, added {padding_added} bytes padding): {padded_data.hex()[:60]}...")
            
            # 4. Encrypt with Rijndael (AES) using fixed IV
            cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
            encrypted_data = cipher.encrypt(padded_data)
            logger.debug(f"Encrypted data ({len(encrypted_data)} bytes): {encrypted_data.hex()[:60]}...")
            
            # 5. Base64 encode WITHOUT including IV - to match Delphi behavior
            result = base64.b64encode(encrypted_data).decode('ascii')
            logger.debug(f"Base64 result (length: {len(result)}): '{result[:60]}...'")
            
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
            
            # 1. Base64 decode
            try:
                decoded = base64.b64decode(data)
                logger.debug(f"Base64 decoded ({len(decoded)} bytes): {decoded.hex()[:60]}...")
            except Exception as e:
                logger.error(f"Base64 decoding failed: {e}")
                raise
            
            # 2. Decrypt with Rijndael (AES) using fixed IV
            try:
                cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
                decrypted = cipher.decrypt(decoded)
                logger.debug(f"Decrypted data ({len(decrypted)} bytes): {decrypted.hex()[:60]}...")
            except Exception as e:
                logger.error(f"AES decryption failed: {e}")
                raise
            
            # 3. Decompress with zlib - let zlib handle trailing zeros
            try:
                decompressed = zlib.decompress(decrypted)
                logger.debug(f"Decompressed data ({len(decompressed)} bytes): {decompressed.hex()[:60]}...")
            except zlib.error as e:
                logger.warning(f"Initial zlib decompression failed: {e}")
                # Try to find the actual compressed data by checking for zlib header
                # (similar to how Delphi StringStream might behave)
                zlib_header = b'\x78'  # Most zlib streams start with 0x78
                if zlib_header in decrypted[:4]:
                    pos = decrypted.find(zlib_header)
                    if pos > -1:
                        logger.debug(f"Found zlib header at position {pos}: {decrypted[pos:pos+4].hex()}")
                        decrypted = decrypted[pos:]
                        try:
                            decompressed = zlib.decompress(decrypted)
                            logger.debug(f"Decompressed after header fix ({len(decompressed)} bytes): {decompressed.hex()[:60]}...")
                        except Exception as e:
                            logger.error(f"Secondary decompression failed even after finding zlib header: {e}")
                            raise
                    else:
                        raise
                else:
                    # Try removing trailing zeros (Delphi might add zeros for block alignment)
                    try:
                        # Remove trailing zeros
                        clean_data = decrypted.rstrip(b'\x00')
                        logger.debug(f"Cleaned data ({len(clean_data)} bytes, removed {len(decrypted)-len(clean_data)} bytes): {clean_data.hex()[:60]}...")
                        decompressed = zlib.decompress(clean_data)
                        logger.debug(f"Decompressed after cleaning ({len(decompressed)} bytes): {decompressed.hex()[:60]}...")
                    except Exception as e:
                        logger.error(f"Secondary decompression failed even after cleaning: {e}")
                        raise
            
            # 4. Try different character encodings
            try:
                result = self._try_decode_multiple_encodings(decompressed)
                logger.debug(f"Decoded result (length: {len(result)}): '{result[:60]}...'")
            except Exception as e:
                logger.error(f"Character decoding failed: {e}")
                raise
            
            return result
            
        except Exception as e:
            self.last_error = f"Error decompressing data: {str(e)}"
            logger.error(self.last_error)
            logger.error(f"Decompression failed: {e}", exc_info=True)
            return "" 
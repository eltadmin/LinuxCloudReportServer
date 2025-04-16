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
            logger.debug(f"Input data (length: {len(data)}): '{data[:100]}...' (truncated if long)")
            
            # 1. Convert string to bytes using appropriate encoding for Cyrillic
            try:
                input_bytes = self._try_multiple_encodings(data)
                logger.debug(f"Input bytes ({len(input_bytes)} bytes): {input_bytes[:30].hex()}...")
            except Exception as e:
                logger.error(f"Error converting string to bytes: {e}")
                raise
            
            # 2. Compress data with zlib at level 6 (Delphi default)
            try:
                compressed_data = zlib.compress(input_bytes, level=6)
                logger.debug(f"Compressed data ({len(compressed_data)} bytes): {compressed_data[:30].hex()}...")
            except Exception as e:
                logger.error(f"Error compressing data: {e}")
                raise
            
            # 3. Ensure data is a multiple of the block size (similar to Delphi's StringStream behavior)
            try:
                padded_data = self._ensure_block_size(compressed_data)
                padding_added = len(padded_data) - len(compressed_data)
                logger.debug(f"Padded data ({len(padded_data)} bytes, added {padding_added} bytes padding): {padded_data[:30].hex()}...")
            except Exception as e:
                logger.error(f"Error padding data: {e}")
                raise
            
            # 4. Encrypt with Rijndael (AES) using fixed IV
            try:
                cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
                encrypted_data = cipher.encrypt(padded_data)
                logger.debug(f"Encrypted data ({len(encrypted_data)} bytes): {encrypted_data[:30].hex()}...")
            except Exception as e:
                logger.error(f"Error encrypting data: {e}")
                raise
            
            # 5. Base64 encode WITHOUT including IV - to match Delphi behavior
            try:
                result = base64.b64encode(encrypted_data).decode('ascii')
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
            
            # 1. Base64 decode
            try:
                # Try to fix common base64 padding issues preemptively
                fixed_data = data
                if len(data) % 4 != 0:
                    missing_padding = 4 - len(data) % 4
                    fixed_data = data + "=" * missing_padding
                    logger.debug(f"Preemptively fixed base64 padding: added {missing_padding} padding characters")
                
                try:
                    decoded = base64.b64decode(fixed_data)
                    logger.debug(f"Base64 decoded with padding fix ({len(decoded)} bytes): {decoded[:30].hex()}...")
                except Exception as e:
                    # If that failed, try the original data
                    logger.debug(f"Fixed padding decode failed, trying original data: {e}")
                    decoded = base64.b64decode(data)
                    logger.debug(f"Base64 decoded with original data ({len(decoded)} bytes): {decoded[:30].hex()}...")
            except Exception as e:
                logger.error(f"Base64 decoding failed: {e}")
                
                # Try other common fixes for base64 data
                try:
                    # Try removing any non-base64 characters that might have been added
                    cleaned_data = ''.join(c for c in data if c in 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=')
                    logger.debug(f"Cleaned data of non-base64 characters: {cleaned_data}")
                    
                    # Fix padding if needed
                    if len(cleaned_data) % 4 != 0:
                        cleaned_data = cleaned_data + "=" * (4 - len(cleaned_data) % 4)
                        logger.debug(f"Fixed base64 padding after cleaning: {cleaned_data}")
                    
                    decoded = base64.b64decode(cleaned_data)
                    logger.debug(f"Base64 decoded after cleaning ({len(decoded)} bytes): {decoded[:30].hex()}...")
                except Exception as e2:
                    logger.error(f"All base64 decoding attempts failed: {e2}")
                    raise e  # Raise original error
            
            # Check if length is a multiple of AES block size (16 bytes) and fix if needed
            if len(decoded) % 16 != 0:
                padding_needed = 16 - (len(decoded) % 16)
                logger.debug(f"Adding padding to make data length multiple of 16: {padding_needed} bytes")
                # Add PKCS#7 style padding (padding byte value = padding length)
                decoded = decoded + bytes([padding_needed] * padding_needed)
                logger.debug(f"Data after padding: {len(decoded)} bytes")
            
            # 2. Decrypt with Rijndael (AES) using fixed IV
            try:
                cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
                decrypted = cipher.decrypt(decoded)
                logger.debug(f"Decrypted data ({len(decrypted)} bytes): {decrypted[:30].hex()}...")
            except Exception as e:
                logger.error(f"AES decryption failed: {e}")
                raise
            
            # 3. Decompress with zlib - let zlib handle trailing zeros
            try:
                # First attempt direct decompression
                try:
                    decompressed = zlib.decompress(decrypted)
                    logger.debug(f"Decompressed data directly ({len(decompressed)} bytes): {decompressed[:30].hex()}...")
                except zlib.error as e:
                    logger.warning(f"Direct zlib decompression failed: {e}")
                    
                    # Remove trailing zeros (Delphi might add zeros for block alignment)
                    clean_data = decrypted.rstrip(b'\x00')
                    logger.debug(f"Cleaned data ({len(clean_data)} bytes, removed {len(decrypted)-len(clean_data)} bytes): {clean_data[:30].hex()}...")
                    
                    try:
                        decompressed = zlib.decompress(clean_data)
                        logger.debug(f"Decompressed after cleaning ({len(decompressed)} bytes): {decompressed[:30].hex()}...")
                    except zlib.error as e2:
                        logger.warning(f"Decompression after cleaning failed: {e2}")
                        
                        # Try to find the zlib header (78 9C is common)
                        try:
                            # Try different starting points
                            for i in range(min(16, len(clean_data))):
                                try:
                                    decompressed = zlib.decompress(clean_data[i:])
                                    logger.debug(f"Decompressed after skip {i} bytes ({len(decompressed)} bytes): {decompressed[:30].hex()}...")
                                    break
                                except Exception:
                                    continue
                            else:
                                raise e2  # Re-raise if all attempts failed
                        except Exception as e3:
                            logger.error(f"All decompression attempts failed: {e3}")
                            raise e  # Raise original error
            except Exception as e:
                logger.error(f"Decompression failed: {e}")
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
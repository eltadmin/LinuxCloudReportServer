#!/usr/bin/env python3
"""
Debug client to test the server's responses to commands
"""

import socket
import logging
import sys
import time
import base64
import zlib
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

# Configure logging
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger("debug_client")

# Copy of the constants to make this script standalone
CRYPTO_DICTIONARY = [
    '123hk12h8dcal',
    'FT676Ugug6sFa',
    'a6xbBa7A8a9la',
    'qMnxbtyTFvcqi',
    'cx7812vcxFRCC',
    'bab7u682ftysv',
    'YGbsux&Ygsyxg',
    'MSN><hu8asG&&',
    '23yY88syHXvvs',
    '987sX&sysy891'
]

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
            
        # Hash the key to get a consistent length for AES
        import hashlib
        try:
            key_bytes = self.crypto_key.encode('cp1251', errors='replace')
            key_hash = hashlib.md5(key_bytes).digest()
            self.aes_key = key_hash
            logger.debug(f"Prepared AES key (MD5 hash): {key_hash.hex()}")
        except Exception as e:
            logger.error(f"Error preparing key: {e}")
            self.aes_key = hashlib.md5(str(self.crypto_key).encode('utf-8', errors='replace')).digest()
        
        # Create a fixed IV of zeros to match Delphi's behavior
        self.iv = b'\x00' * 16
        
    def compress_data(self, data):
        """Compresses and encrypts data using the crypto key - matches Delphi implementation"""
        try:
            logger.debug(f"Compressing data: '{data}'")
            
            # Convert string to bytes
            input_bytes = data.encode('cp1251', errors='replace')
            
            # Compress data with zlib
            compressed_data = zlib.compress(input_bytes, level=6)
            
            # Ensure data is a multiple of the block size
            if len(compressed_data) % 16 != 0:
                padding_needed = 16 - (len(compressed_data) % 16)
                compressed_data += b'\x00' * padding_needed
            
            # Encrypt with AES using fixed IV
            cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
            encrypted_data = cipher.encrypt(compressed_data)
            
            # Base64 encode
            result = base64.b64encode(encrypted_data).decode('ascii')
            logger.debug(f"Compressed result: '{result[:30]}...' ({len(result)} bytes)")
            
            return result
            
        except Exception as e:
            self.last_error = f"Error compressing data: {str(e)}"
            logger.error(self.last_error)
            return ""
            
    def decompress_data(self, data):
        """Decrypts and decompresses data using the crypto key - matches Delphi implementation"""
        try:
            logger.debug(f"Decompressing data: '{data[:30]}...' ({len(data)} bytes)")
            
            # Base64 decode
            decoded = base64.b64decode(data)
            
            # Decrypt with AES
            cipher = AES.new(self.aes_key, AES.MODE_CBC, self.iv)
            decrypted = cipher.decrypt(decoded)
            
            # Remove trailing zeros (if any)
            while decrypted and decrypted[-1] == 0:
                decrypted = decrypted[:-1]
            
            # Decompress with zlib
            try:
                decompressed = zlib.decompress(decrypted)
            except:
                # If decompression fails, try different slices
                for i in range(16):
                    try:
                        decompressed = zlib.decompress(decrypted[i:])
                        break
                    except:
                        continue
                else:
                    raise ValueError("Failed to decompress data")
            
            # Convert bytes to string
            result = decompressed.decode('cp1251', errors='replace')
            logger.debug(f"Decompressed result: '{result}'")
            
            return result
            
        except Exception as e:
            self.last_error = f"Error decompressing data: {str(e)}"
            logger.error(self.last_error)
            return ""

def init_connection(host, port):
    """Initialize connection with the server"""
    client_id = 9  # Changed to 9 to test the problematic client ID
    hostname = "DEBUG-CLIENT"
    
    # Connect to server
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.connect((host, port))
    logger.info(f"Connected to {host}:{port}")
    
    # Send INIT command
    init_cmd = f"INIT ID={client_id} DT=250414 TM=140000 HST={hostname} ATP=DebugClient AVR=1.0.0.0\r\n"
    logger.info(f"Sending INIT: {init_cmd.strip()}")
    sock.sendall(init_cmd.encode('ascii'))
    
    # Wait for response
    response = b""
    while True:
        data = sock.recv(1024)
        if not data:
            break
        response += data
        if b"\r\n\r\n" in data:  # End of response
            break
    
    logger.info(f"INIT Response raw bytes: {' '.join([f'{b:02x}' for b in response])}")
    logger.info(f"INIT Response string: '{response.decode('ascii', errors='replace')}'")
    
    # Parse INIT response
    try:
        # Delphi TStrings.Values format
        lines = response.decode('ascii').strip().split("\r\n")
        len_value = None
        key_value = None
        
        # Try to find LEN and KEY values
        for line in lines:
            if line.startswith("LEN="):
                len_value = line[4:]
            elif line.startswith("KEY="):
                key_value = line[4:]
        
        if not key_value:
            # Try other formats
            for line in lines:
                if "=" in line:
                    key, value = line.split("=", 1)
                    if key.strip() == "KEY":
                        key_value = value.strip()
                    elif key.strip() == "LEN":
                        len_value = value.strip()
        
        logger.info(f"Parsed KEY={key_value}, LEN={len_value}")
        
        if not key_value:
            logger.error("Failed to parse KEY from response")
            sock.close()
            return None
        
        server_key = key_value
        
        # Calculate crypto key
        key_len = 8 if not len_value else int(len_value)
        dict_part = CRYPTO_DICTIONARY[client_id - 1][:key_len]
        host_part = hostname[:2] + hostname[-1]
        crypto_key = server_key + dict_part + host_part
        
        logger.info(f"Calculated crypto key: {crypto_key}")
        logger.info(f"  server_key: {server_key}")
        logger.info(f"  dict_part: {dict_part}")
        logger.info(f"  host_part: {host_part}")
        
        # Create standard compressor with calculated key
        compressor = DataCompressor(crypto_key)
        
        # Also create a compressor with the hardcoded key for ID=9
        hardcoded_key = "D5F22NE-"
        hardcoded_compressor = DataCompressor(hardcoded_key)
        logger.info(f"Created hardcoded compressor with key: {hardcoded_key}")
        
        # Test encryption with standard key
        test_string = "TT=Test"
        encrypted = compressor.compress_data(test_string)
        decrypted = compressor.decompress_data(encrypted)
        
        if decrypted == test_string:
            logger.info("Standard encryption test successful!")
        else:
            logger.warning(f"Standard encryption test failed. Expected '{test_string}', got '{decrypted}'")
        
        # Test encryption with hardcoded key
        hardcoded_encrypted = hardcoded_compressor.compress_data(test_string)
        hardcoded_decrypted = hardcoded_compressor.decompress_data(hardcoded_encrypted)
        
        if hardcoded_decrypted == test_string:
            logger.info("Hardcoded key encryption test successful!")
        else:
            logger.warning(f"Hardcoded key encryption test failed. Expected '{test_string}', got '{hardcoded_decrypted}'")
        
        # Send INFO command with the standard key first
        info_data = "ID=DebugClient\r\nTT=Test\r\nHOST=DEBUG-CLIENT"
        encrypted_info = compressor.compress_data(info_data)
        info_cmd = f"INFO DATA={encrypted_info}\r\n"
        
        logger.info(f"Sending INFO command with standard key. Data: {info_data}")
        sock.sendall(info_cmd.encode('ascii'))
        
        # Wait for response
        response = b""
        while True:
            data = sock.recv(1024)
            if not data:
                break
            response += data
            if b"\r\n\r\n" in data:  # End of response
                break
        
        logger.info(f"INFO Response (standard key): {response.decode('ascii', errors='replace')}")
        
        # Try to parse and decrypt the response
        if b"DATA=" in response:
            data_start = response.find(b"DATA=") + 5
            encrypted_resp = response[data_start:].strip()
            
            try:
                # Try to decrypt with both keys
                logger.info("Attempting to decrypt with standard key")
                standard_decrypted = compressor.decompress_data(encrypted_resp.decode('ascii'))
                logger.info(f"Decrypted INFO response (standard key): {standard_decrypted}")
            except Exception as e:
                logger.error(f"Failed to decrypt INFO response with standard key: {e}")
                
            try:
                logger.info("Attempting to decrypt with hardcoded key")
                hardcoded_decrypted = hardcoded_compressor.decompress_data(encrypted_resp.decode('ascii'))
                logger.info(f"Decrypted INFO response (hardcoded key): {hardcoded_decrypted}")
            except Exception as e:
                logger.error(f"Failed to decrypt INFO response with hardcoded key: {e}")
        
        # Now send another INFO command with the hardcoded key
        encrypted_info_hardcoded = hardcoded_compressor.compress_data(info_data)
        info_cmd_hardcoded = f"INFO DATA={encrypted_info_hardcoded}\r\n"
        
        logger.info(f"Sending INFO command with hardcoded key. Data: {info_data}")
        sock.sendall(info_cmd_hardcoded.encode('ascii'))
        
        # Wait for response
        response = b""
        while True:
            data = sock.recv(1024)
            if not data:
                break
            response += data
            if b"\r\n\r\n" in data:  # End of response
                break
        
        logger.info(f"INFO Response (hardcoded key): {response.decode('ascii', errors='replace')}")
        
        # Try to parse and decrypt the response
        if b"DATA=" in response:
            data_start = response.find(b"DATA=") + 5
            encrypted_resp = response[data_start:].strip()
            
            try:
                # Try to decrypt with both keys
                logger.info("Attempting to decrypt with standard key")
                standard_decrypted = compressor.decompress_data(encrypted_resp.decode('ascii'))
                logger.info(f"Decrypted INFO response (standard key): {standard_decrypted}")
            except Exception as e:
                logger.error(f"Failed to decrypt INFO response with standard key: {e}")
                
            try:
                logger.info("Attempting to decrypt with hardcoded key")
                hardcoded_decrypted = hardcoded_compressor.decompress_data(encrypted_resp.decode('ascii'))
                logger.info(f"Decrypted INFO response (hardcoded key): {hardcoded_decrypted}")
            except Exception as e:
                logger.error(f"Failed to decrypt INFO response with hardcoded key: {e}")
        
        return sock, compressor, hardcoded_compressor
        
    except Exception as e:
        logger.error(f"Error processing INIT response: {e}", exc_info=True)
        sock.close()
        return None

def main():
    """Main function"""
    host = "127.0.0.1"  # Changed from localhost to explicit IP
    port = 8016
    
    if len(sys.argv) > 1:
        host = sys.argv[1]
    if len(sys.argv) > 2:
        port = int(sys.argv[2])
    
    logger.info(f"Starting debug client connecting to {host}:{port}")
    
    try:
        result = init_connection(host, port)
        if result:
            sock, compressor, hardcoded_compressor = result
            logger.info("Connection initialized successfully")
            
            # Close the socket
            sock.close()
        else:
            logger.error("Failed to initialize connection")
            
    except Exception as e:
        logger.error(f"Error in debug client: {e}", exc_info=True)

if __name__ == "__main__":
    main() 
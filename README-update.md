# Server Update Instructions

This document describes the necessary fixes to the TCP server to resolve the "Unable to initialize communication!" error that clients are experiencing.

## Problem Description

The cloud report client is sending an error message when trying to connect to the server:
```
Received command: ERRL [Error]Unable to initizlize communication! Terminate connection
```

This error occurs during the INIT handshake process. The issue is that the Python server implementation doesn't properly implement the crypto key generation protocol that the Delphi client expects.

## Required Changes

The following files need to be created or modified:

### 1. Add CRYPTO_DICTIONARY to `server/__init__.py`

```python
# Dictionary used for crypto key generation - must match client dictionary exactly
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

from .server import ReportServer
from .tcp_server import TCPServer
from .http_server import HTTPServer
from .db import Database
from .crypto import DataCompressor

__all__ = ['ReportServer', 'TCPServer', 'HTTPServer', 'Database', 'DataCompressor']
```

### 2. Create a new file `server/crypto.py` for data compression/encryption

```python
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
```

### 3. Update the TCPConnection class in `server/tcp_server.py`

Add these properties and methods to the TCPConnection class:

```python
# Add to imports at the top of the file:
from . import CRYPTO_DICTIONARY
from .crypto import DataCompressor

# Add properties to TCPConnection.__init__:
self.crypto_key = None
self.last_error = None

# Add these methods to the TCPConnection class:
def encrypt_data(self, data):
    """Encrypts data using the crypto key"""
    if not self.crypto_key:
        self.last_error = "Crypto key is not negotiated"
        return None
        
    compressor = DataCompressor(self.crypto_key)
    result = compressor.compress_data(data)
    
    if not result:
        self.last_error = compressor.last_error
        return None
        
    return result
    
def decrypt_data(self, data):
    """Decrypts data using the crypto key"""
    if not self.crypto_key:
        self.last_error = "Crypto key is not negotiated"
        return None
        
    compressor = DataCompressor(self.crypto_key)
    result = compressor.decompress_data(data)
    
    if not result:
        self.last_error = compressor.last_error
        return None
        
    return result
```

### 4. Update the handle_command method for INIT in `server/tcp_server.py`

Replace the key generation in the INIT command handler:

```python
# Replace the existing key generation code with:
# Generate crypto key
# The key length is used to determine how many characters to take from the dictionary entry
key_len = random.randint(1, 12)
server_key = ''.join(random.choices(string.ascii_letters + string.digits, k=8))

# Store crypto key components
conn.server_key = server_key
conn.key_length = key_len

# In the original implementation, the full crypto key combines:
# 1. The server_key (random 8 chars)
# 2. A portion of the crypto dictionary entry for the given key_id
# 3. First 2 chars of client host + last char of client host
# This is crucial for the client to correctly compute the same key
crypto_dict_part = CRYPTO_DICTIONARY[key_id - 1][:key_len]
host_part = conn.client_host[:2] + conn.client_host[-1:]
conn.crypto_key = server_key + crypto_dict_part + host_part

logger.info(f"Generated crypto key: server_key={server_key}, length={key_len}, full_key={conn.crypto_key}")
```

## Deploying the Changes

After making these changes:

1. Save all files
2. Rebuild the Docker containers:
   ```
   docker-compose down
   docker-compose up -d --build
   ```

3. Check the logs to verify the server is working correctly:
   ```
   docker-compose logs -f
   ```

## Technical Explanation

The original Delphi client and server use a specific protocol for establishing a secure connection:

1. Client sends INIT command with a key ID (1-10)
2. Server generates a random server key and key length
3. Both sides compute the same crypto key using:
   - Server-generated random key
   - A portion of a shared secret dictionary (indexed by the key ID)
   - Parts of the client's hostname

The Python server implementation was missing the critical dictionary and wasn't computing the full crypto key correctly, causing the client to fail when trying to encrypt/decrypt data. 
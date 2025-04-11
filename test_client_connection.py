#!/usr/bin/env python3
"""
Test script to verify the TCP server functionality by simulating client connections.
This script replicates the basic protocol used by the BosTcpClient application.
"""

import asyncio
import logging
import argparse
import random
import string
import json
import zlib
import base64
import hashlib
from datetime import datetime
import sys

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
)
logger = logging.getLogger("test_client")

# Same crypto dictionary used by the server and original client
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

class TestClient:
    def __init__(self, host, port, client_id='TEST_CLIENT'):
        self.host = host
        self.port = port
        self.client_id = client_id
        self.client_host = "TESTHOST"
        self.app_type = "TESTAPP"
        self.app_version = "1.0.0"
        self.reader = None
        self.writer = None
        self.crypto_key = None
        self.server_key = None
        self.key_length = None
        
    async def connect(self):
        """Connect to the TCP server"""
        logger.info(f"Connecting to {self.host}:{self.port}")
        try:
            self.reader, self.writer = await asyncio.open_connection(self.host, self.port)
            logger.info("Connection established")
            return True
        except Exception as e:
            logger.error(f"Connection failed: {e}")
            return False
    
    async def disconnect(self):
        """Disconnect from the server"""
        if self.writer:
            self.writer.close()
            await self.writer.wait_closed()
            logger.info("Disconnected from server")
    
    async def send_command(self, command):
        """Send a command to the server and return the response"""
        if not self.writer:
            logger.error("Not connected")
            return None
            
        # Add newline terminator if not present
        if not command.endswith('\n'):
            command += '\n'
            
        logger.debug(f"Sending: {command.strip()}")
        self.writer.write(command.encode())
        await self.writer.drain()
        
        # Read response line by line until empty line or timeout
        response_lines = []
        try:
            while True:
                data = await asyncio.wait_for(self.reader.readline(), timeout=5.0)
                line = data.decode().strip()
                if not line:
                    break
                response_lines.append(line)
        except asyncio.TimeoutError:
            logger.warning("Timeout reading response")
            
        response = '\n'.join(response_lines)
        logger.debug(f"Received: {response}")
        return response
    
    def encrypt_data(self, data):
        """Encrypt data using the negotiated crypto key"""
        if not self.crypto_key:
            logger.error("No crypto key available")
            return None
            
        try:
            # Compress data
            compressed = zlib.compress(data.encode('utf-8'))
            
            # Convert to base64 for string handling
            encoded = base64.b64encode(compressed).decode('utf-8')
            
            # XOR with MD5 hash of crypto key
            key_hash = hashlib.md5(self.crypto_key.encode('utf-8')).digest()
            result = []
            
            for i, char in enumerate(encoded):
                key_char = key_hash[i % len(key_hash)]
                xored = chr(ord(char) ^ key_char)
                result.append(xored)
                
            return ''.join(result)
        except Exception as e:
            logger.error(f"Encryption error: {e}")
            return None
    
    def decrypt_data(self, data):
        """Decrypt data using the negotiated crypto key"""
        if not self.crypto_key:
            logger.error("No crypto key available")
            return None
            
        try:
            # XOR with MD5 hash of crypto key
            key_hash = hashlib.md5(self.crypto_key.encode('utf-8')).digest()
            result = []
            
            for i, char in enumerate(data):
                key_char = key_hash[i % len(key_hash)]
                xored = chr(ord(char) ^ key_char)
                result.append(xored)
                
            # Decode from base64
            decoded = base64.b64decode(''.join(result))
            
            # Decompress
            decompressed = zlib.decompress(decoded)
            
            return decompressed.decode('utf-8')
        except Exception as e:
            logger.error(f"Decryption error: {e}")
            return None
    
    async def initialize(self):
        """Perform the initialization handshake with the server"""
        # Generate a random key ID between 1 and 10
        key_id = random.randint(1, 10)
        now = datetime.now()
        date_str = now.strftime('%y%m%d')  # YYMMDD format
        time_str = now.strftime('%H%M%S')  # HHMMSS format
        
        # Send INIT command
        init_cmd = f"INIT ID={key_id} DT={date_str} TM={time_str} HST={self.client_host} ATP={self.app_type} AVR={self.app_version}"
        response = await self.send_command(init_cmd)
        
        if not response or not response.startswith('200 OK'):
            logger.error(f"Initialization failed: {response}")
            return False
            
        # Parse the response to get key length and server key
        lines = response.split('\r\n')
        len_line = next((l for l in lines if l.startswith('LEN=')), None)
        key_line = next((l for l in lines if l.startswith('KEY=')), None)
        
        if not len_line or not key_line:
            logger.error(f"Missing LEN or KEY in response: {response}")
            return False
            
        self.key_length = int(len_line.split('=')[1])
        self.server_key = key_line.split('=')[1]
        
        # Calculate the crypto key using the same formula as the client and server
        crypto_dict_part = CRYPTO_DICTIONARY[key_id - 1][:self.key_length]
        host_part = self.client_host[:2] + self.client_host[-1:]
        self.crypto_key = self.server_key + crypto_dict_part + host_part
        
        logger.info(f"Initialization successful: key_length={self.key_length}, server_key={self.server_key}")
        logger.debug(f"Crypto key: {self.crypto_key}")
        return True
    
    async def send_info(self):
        """Send client information to the server"""
        # Data to send, including a test validation string
        info_data = f"ID={self.client_id}\nТТ=Test\nVV=1.0"
        
        # Encrypt the data
        encrypted = self.encrypt_data(info_data)
        if not encrypted:
            logger.error("Failed to encrypt INFO data")
            return False
            
        # Send the command with encrypted data
        response = await self.send_command(f"INFO DATA={encrypted}")
        
        if not response or not response.startswith('200 OK'):
            logger.error(f"INFO command failed: {response}")
            return False
            
        # Parse the encrypted response data
        parts = response.split('\n')
        if len(parts) < 2 or not parts[1].startswith('DATA='):
            logger.error(f"Invalid INFO response format: {response}")
            return False
            
        encrypted_response = parts[1][5:]  # Remove DATA= prefix
        decrypted = self.decrypt_data(encrypted_response)
        
        if not decrypted:
            logger.error("Failed to decrypt INFO response")
            return False
            
        logger.info(f"INFO command successful")
        logger.debug(f"Decrypted response: {decrypted}")
        return True
    
    async def send_ping(self):
        """Send PING command to the server"""
        response = await self.send_command("PING")
        
        if response == "PONG":
            logger.info("PING successful")
            return True
        else:
            logger.error(f"PING failed: {response}")
            return False
    
    async def get_updates(self):
        """Get list of available updates from the server"""
        response = await self.send_command("VERS")
        
        try:
            # Try to parse as JSON first (newer servers)
            data = json.loads(response)
            updates = data.get('updates', [])
            logger.info(f"VERS command successful: {len(updates)} updates available")
            for update in updates:
                logger.info(f"  - {update['name']} ({update['size']} bytes)")
            return True
        except json.JSONDecodeError:
            # Check if it's an encrypted response
            if response.startswith('200 OK') and 'DATA=' in response:
                parts = response.split('\n')
                for part in parts:
                    if part.startswith('DATA='):
                        encrypted = part[5:]
                        decrypted = self.decrypt_data(encrypted)
                        if decrypted:
                            try:
                                data = json.loads(decrypted)
                                updates = data.get('updates', [])
                                logger.info(f"VERS command successful: {len(updates)} updates available")
                                for update in updates:
                                    logger.info(f"  - {update['name']} ({update['size']} bytes)")
                                return True
                            except:
                                logger.error(f"Failed to parse decrypted VERS data")
            
            logger.error(f"VERS command failed: {response}")
            return False
    
    async def report_error(self, error_message):
        """Send an error report to the server"""
        response = await self.send_command(f"ERRL {error_message}")
        
        if response == "OK":
            logger.info("Error report sent successfully")
            return True
        else:
            logger.error(f"Error report failed: {response}")
            return False
            
    async def run_test(self):
        """Run a complete test sequence"""
        try:
            # Connect to server
            if not await self.connect():
                return False
                
            # Initialize and negotiate crypto key
            if not await self.initialize():
                return False
                
            # Send client info
            if not await self.send_info():
                return False
                
            # Send ping
            if not await self.send_ping():
                return False
                
            # Get updates list
            if not await self.get_updates():
                return False
                
            # Send a test error report
            if not await self.report_error("Test error message from test client"):
                return False
                
            logger.info("All tests completed successfully!")
            return True
            
        except Exception as e:
            logger.error(f"Test failed with exception: {e}")
            return False
        finally:
            # Ensure we disconnect
            await self.disconnect()

async def main():
    parser = argparse.ArgumentParser(description="Test TCP connection to the Report Server")
    parser.add_argument('--host', default='localhost', help='Server hostname')
    parser.add_argument('--port', type=int, default=8016, help='Server port')
    parser.add_argument('--client-id', default='TEST_CLIENT', help='Client ID to use')
    parser.add_argument('--verbose', '-v', action='store_true', help='Enable verbose logging')
    
    args = parser.parse_args()
    
    if args.verbose:
        logger.setLevel(logging.DEBUG)
    
    logger.info(f"Testing connection to {args.host}:{args.port} with client ID '{args.client_id}'")
    
    client = TestClient(args.host, args.port, args.client_id)
    success = await client.run_test()
    
    if success:
        logger.info("✅ All tests passed!")
        return 0
    else:
        logger.error("❌ Test failed!")
        return 1

if __name__ == "__main__":
    sys.exit(asyncio.run(main())) 
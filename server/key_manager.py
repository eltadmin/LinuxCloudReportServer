"""
Key Management Module for the Linux Cloud Report Server.

This module handles the generation and management of cryptographic keys.
"""

import logging
from .constants import CRYPTO_DICTIONARY, DEFAULT_SERVER_KEY, SPECIAL_KEYS, KEY_LENGTH_BY_ID

logger = logging.getLogger(__name__)

class KeyManager:
    """
    Manages cryptographic keys for client connections.
    
    Implements the key generation algorithm described in the requirements:
    - Keys are generated from: serverKey + dictEntryPart + hostFirstChars + hostLastChar
    - For ID=9, uses the first 2 characters from the dictionary entry
    - For other IDs, uses only the first character from the dictionary entry
    - Special keys are used for ID=5 and ID=9
    """
    
    def __init__(self):
        """Initialize the KeyManager."""
        self.server_key = DEFAULT_SERVER_KEY
        logger.info(f"KeyManager initialized with server key: {self.server_key}")
    
    def get_key_for_client_id(self, client_id, host):
        """
        Generate the appropriate cryptographic key for a client ID.
        
        Args:
            client_id: The client ID (1-10)
            host: The client's host IP address
            
        Returns:
            A tuple of (server_key, key_length, crypto_key)
        """
        logger.info(f"Generating key for client ID: {client_id}, host: {host}")
        
        # Check for special hardcoded keys first
        if client_id in SPECIAL_KEYS:
            special_key = SPECIAL_KEYS[client_id]
            logger.info(f"Using special key for ID {client_id}: {special_key}")
            return self.server_key, self._get_key_length(client_id), special_key
        
        # Extract host parts
        host_first_chars = self._get_host_first_chars(host)
        host_last_char = self._get_host_last_char(host)
        
        # Get dictionary entry part based on ID and key length
        dict_entry_part = self._get_dict_entry_part(client_id)
        
        # Construct the crypto key
        crypto_key = f"{self.server_key}{dict_entry_part}{host_first_chars}{host_last_char}"
        key_length = self._get_key_length(client_id)
        
        logger.info(f"Generated key components - server key: {self.server_key}, dict part: {dict_entry_part}, "
                   f"host first: {host_first_chars}, host last: {host_last_char}")
        logger.info(f"Final crypto key: {crypto_key}")
        
        return self.server_key, key_length, crypto_key
    
    def _get_dict_entry_part(self, client_id):
        """
        Get the dictionary entry part for the given client ID.
        
        Args:
            client_id: The client ID (1-10)
            
        Returns:
            The appropriate part of the dictionary entry
        """
        # Ensure client_id is within range
        if client_id < 1 or client_id > len(CRYPTO_DICTIONARY):
            logger.warning(f"Client ID {client_id} out of range, using ID 1")
            client_id = 1
        
        # Get the dictionary entry (0-indexed)
        dict_entry = CRYPTO_DICTIONARY[client_id - 1]
        
        # Determine how many characters to use based on the client ID
        key_length = self._get_key_length(client_id)
        
        # Extract the appropriate part
        dict_part = dict_entry[:key_length]
        logger.debug(f"Dictionary entry for ID {client_id}: {dict_entry}, using part: {dict_part}")
        
        return dict_part
    
    def _get_key_length(self, client_id):
        """
        Get the key length for a specific client ID.
        
        Args:
            client_id: The client ID
            
        Returns:
            The key length to use (1 or 2)
        """
        # Check if this ID has a specific key length defined
        return KEY_LENGTH_BY_ID.get(client_id, 1)
    
    def _get_host_first_chars(self, host):
        """
        Get the first two characters of the host.
        
        Args:
            host: The host IP address
            
        Returns:
            The first two characters of the host, or 'NE' if not available
        """
        if host and len(host) >= 2:
            return host[:2]
        return "NE"  # Default value
    
    def _get_host_last_char(self, host):
        """
        Get the last character of the host.
        
        Args:
            host: The host IP address
            
        Returns:
            The last character of the host, or '-' if not available
        """
        if host and len(host) >= 1:
            return host[-1]
        return "-"  # Default value 
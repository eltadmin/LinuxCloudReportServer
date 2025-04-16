"""
Message Handler Module for the Linux Cloud Report Server.

This module handles the formatting and parsing of messages between client and server.
"""

import logging
import time
from datetime import datetime
from typing import Dict, List, Tuple, Any, Optional

logger = logging.getLogger(__name__)

class MessageHandler:
    """
    Handles message formatting and parsing for the TCP server.
    """
    
    def __init__(self):
        """Initialize the MessageHandler."""
        logger.info("MessageHandler initialized")
    
    def format_init_response(self, server_key: str, key_length: int) -> str:
        """
        Format the INIT response to send back to the client.
        
        Args:
            server_key: The server key to include in the response
            key_length: The length of the key
            
        Returns:
            Formatted response string in the format: "200-KEY=xxx\r\n200 LEN=y\r\n"
        """
        response = f"200-KEY={server_key}\r\n200 LEN={key_length}\r\n"
        logger.debug(f"Formatted INIT response: '{response}'")
        return response
    
    def format_error_response(self, error_message: str) -> str:
        """
        Format an error response to send back to the client.
        
        Args:
            error_message: The error message
            
        Returns:
            Formatted error response string
        """
        response = f"ERROR {error_message}\r\n"
        logger.debug(f"Formatted error response: '{response}'")
        return response
    
    def format_data_response(self, data: Dict[str, Any], use_newlines: bool = True) -> str:
        """
        Format a data response to send back to the client.
        
        Args:
            data: Dictionary of key-value pairs to include in the response
            use_newlines: Whether to use newlines (CRLF) between key-value pairs
            
        Returns:
            Formatted data response string with TT=Test as the first field
        """
        # Always put TT=Test first as required
        items = [f"TT=Test"]
        
        # Add the rest of the items
        for key, value in data.items():
            if key != "TT":  # Skip TT if it was in the data dict
                items.append(f"{key}={value}")
        
        # Join with appropriate separator
        if use_newlines:
            separator = "\r\n"
        else:
            separator = " "
        
        response = separator.join(items)
        logger.debug(f"Formatted data response: '{response[:100]}...' (truncated if long)")
        return response
    
    def format_standard_response(self, data: str) -> str:
        """
        Format a standard success response with data.
        
        Args:
            data: The data to include in the response
            
        Returns:
            Formatted standard response string in the format: "200 OK\r\nDATA=xxx"
        """
        response = f"200 OK\r\nDATA={data}\r\n"
        logger.debug(f"Formatted standard response: '{response[:100]}...' (truncated if long)")
        return response
    
    def parse_command(self, command_str: str) -> Tuple[str, Dict[str, str]]:
        """
        Parse a command string into command name and parameters.
        
        Args:
            command_str: The command string from the client
            
        Returns:
            Tuple of (command_name, parameters_dict)
        """
        logger.debug(f"Parsing command: '{command_str}'")
        
        # Split the command into parts
        parts = command_str.strip().split()
        if not parts:
            logger.warning("Empty command string")
            return "", {}
        
        # The first part is the command name
        command_name = parts[0].upper()
        
        # The rest are parameters
        params = {}
        for part in parts[1:]:
            if '=' in part:
                key, value = part.split('=', 1)
                params[key.upper()] = value
            else:
                # For parameters without a value, use the parameter as the key with an empty value
                params[part.upper()] = ""
        
        logger.debug(f"Parsed command: {command_name}, parameters: {params}")
        return command_name, params
    
    def parse_data_parameter(self, data_param: str) -> Dict[str, str]:
        """
        Parse the DATA parameter into a dictionary of key-value pairs.
        
        Args:
            data_param: The DATA parameter value
            
        Returns:
            Dictionary of key-value pairs
        """
        logger.debug(f"Parsing DATA parameter: '{data_param[:100]}...' (truncated if long)")
        
        result = {}
        
        # Try different separators in order of preference
        separators = ["\r\n", "\n", ";"]
        
        for separator in separators:
            if separator in data_param:
                pairs = data_param.split(separator)
                
                for pair in pairs:
                    if '=' in pair:
                        key, value = pair.split('=', 1)
                        result[key.strip()] = value.strip()
                
                # If we found at least one key-value pair, we're done
                if result:
                    logger.debug(f"Parsed {len(result)} key-value pairs using separator '{separator!r}'")
                    break
        
        # If we couldn't parse with separators, try as a single key-value pair
        if not result and '=' in data_param:
            key, value = data_param.split('=', 1)
            result[key.strip()] = value.strip()
            logger.debug("Parsed single key-value pair without separator")
        
        logger.debug(f"Parsed data: {result}")
        return result
    
    def generate_info_response(self, client_id: str) -> Dict[str, str]:
        """
        Generate response data for the INFO command.
        
        Args:
            client_id: The client ID
            
        Returns:
            Dictionary of response data with required fields
        """
        # Get current date and time
        now = datetime.now()
        creation_date = now.strftime("%Y-%m-%d")
        creation_time = now.strftime("%H:%M:%S")
        
        # Calculate expiry date (1 year from now)
        expiry_date = (now.replace(year=now.year + 1)).strftime("%Y-%m-%d")
        
        # Required fields for INFO response
        response_data = {
            "TT": "Test",              # Validation field
            "ID": client_id,           # Client ID
            "EX": expiry_date,         # Expiry date
            "EN": "1",                 # Enabled (1=yes)
            "CD": creation_date,       # Creation date
            "CT": creation_time        # Creation time
        }
        
        logger.debug(f"Generated INFO response data: {response_data}")
        return response_data 
import logging
import re
from enum import Enum, auto

logger = logging.getLogger(__name__)

class MessageType(Enum):
    UNKNOWN = auto()
    ERROR = auto()
    DATA = auto()
    COMMAND = auto()
    RESPONSE = auto()

class Message:
    def __init__(self, content=None, encoding="utf-8", is_error=False, message_type=MessageType.UNKNOWN):
        self.content = content
        self.encoding = encoding
        self.is_error = is_error
        self.message_type = message_type
        if content:
            logger.debug(f"Message initialized with content type {type(content)}, length: {len(str(content))}, "
                        f"encoding: {encoding}, is_error: {is_error}, message_type: {message_type}")
            if isinstance(content, str) and len(content) < 1000:
                logger.debug(f"Message content (string): '{content}'")
            elif isinstance(content, bytes) and len(content) < 1000:
                logger.debug(f"Message content (bytes): {content[:100].hex()}")
            else:
                logger.debug(f"Message content too large to log (length: {len(str(content))})")

    def set_content(self, content, encoding="utf-8"):
        self.content = content
        self.encoding = encoding
        if content:
            logger.debug(f"Message content set with type {type(content)}, length: {len(str(content))}, encoding: {encoding}")
            if isinstance(content, str) and len(content) < 1000:
                logger.debug(f"New message content (string): '{content}'")
            elif isinstance(content, bytes) and len(content) < 1000:
                logger.debug(f"New message content (bytes): {content[:100].hex()}")

    def get_content(self):
        logger.debug(f"Getting message content of type {type(self.content)}")
        return self.content

    def set_error_message(self, content, is_error=True):
        self.content = content
        self.is_error = is_error
        self.message_type = MessageType.ERROR
        logger.debug(f"Set error message: is_error={is_error}, content: '{content}'")

    def _extract_content_values(self, pattern_map, content=None):
        """
        Extracts values from content based on pattern_map.
        pattern_map is a dictionary of {name: regex_pattern}
        Returns a dictionary of {name: value}
        """
        if content is None:
            content = self.content
            
        logger.debug(f"Extracting content values using {len(pattern_map)} patterns")
        
        result = {}
        if not content:
            logger.warning("Cannot extract values from empty content")
            return result

        for name, pattern in pattern_map.items():
            try:
                match = re.search(pattern, content)
                if match:
                    result[name] = match.group(1)
                    # Log the extracted value but truncate if too long
                    value_to_log = result[name]
                    if len(value_to_log) > 100:
                        value_to_log = f"{value_to_log[:100]}... (truncated, full length: {len(result[name])})"
                    logger.debug(f"Extracted '{name}': '{value_to_log}'")
                else:
                    logger.debug(f"Pattern '{pattern}' for '{name}' not found in content")
            except Exception as e:
                logger.error(f"Error extracting '{name}' with pattern '{pattern}': {e}")
                
        logger.debug(f"Extracted {len(result)} values from content")
        return result

    def parse_message(self, parsers=None):
        """
        Parses message content using the provided parsers.
        parsers is a dictionary of {name: (pattern, transform_function)}
        Returns a dictionary of {name: transformed_value}
        """
        if not parsers:
            logger.warning("No parsers provided for message parsing")
            return {}
            
        logger.debug(f"Parsing message with {len(parsers)} parsers")
        
        result = {}
        if not self.content:
            logger.warning("Cannot parse empty message content")
            return result

        for name, (pattern, transform) in parsers.items():
            try:
                logger.debug(f"Applying parser '{name}' with pattern '{pattern}'")
                match = re.search(pattern, self.content)
                if match:
                    raw_value = match.group(1)
                    logger.debug(f"Found match for '{name}': '{raw_value[:100]}...' (truncated if long)")
                    
                    if transform:
                        try:
                            result[name] = transform(raw_value)
                            logger.debug(f"Transformed value for '{name}': type={type(result[name])}")
                        except Exception as e:
                            logger.error(f"Error transforming value for '{name}': {e}")
                            result[name] = raw_value
                    else:
                        result[name] = raw_value
                else:
                    logger.debug(f"No match found for '{name}' with pattern '{pattern}'")
            except Exception as e:
                logger.error(f"Error parsing '{name}' with pattern '{pattern}': {e}")
                
        logger.debug(f"Parsed {len(result)} values from message")
        return result 
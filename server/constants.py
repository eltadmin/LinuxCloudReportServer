"""
Constants for the Report Server
"""

# Default server key as specified in the requirements
DEFAULT_SERVER_KEY = "D5F2"

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

# Special hardcoded keys for specific client IDs
SPECIAL_KEYS = {
    5: "D5F2cNE-",  # Special key for ID=5
    9: "D5F22NE-"   # Special key for ID=9
}

# Key length by client ID
KEY_LENGTH_BY_ID = {
    9: 2,  # Use first 2 characters from dictionary entry
}
# Default key length is 1 for all other IDs

# Response format for INIT command
# Format is: "200-KEY=xxx\r\n200 LEN=y\r\n"
INIT_RESPONSE_FORMAT = "200-KEY={}\r\n200 LEN={}\r\n"

# Command constants
CMD_INIT = 'INIT'
CMD_ERRL = 'ERRL'
CMD_PING = 'PING'
CMD_INFO = 'INFO'
CMD_VERS = 'VERS'
CMD_DWNL = 'DWNL'
CMD_GREQ = 'GREQ'
CMD_SRSP = 'SRSP'

# Default response format for INFO command
DEFAULT_RESP_FORMAT = 1 

# Timeout constants (seconds)
CONNECTION_TIMEOUT = 300  # 5 minutes
INACTIVITY_CHECK_INTERVAL = 60  # 1 minute 
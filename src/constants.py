"""
Constants module for Cloud Report Server
"""

# Log file names
TRACE_LOG_FILENAME = 'TraceLog_Server.txt'

# Connection timeouts
DROP_DEVICE_WITHOUT_SERIAL_TIME_SEC = 60  # Time in seconds after which to drop connections without client ID
DROP_DEVICE_WITHOUT_ACTIVITY_SEC = 120    # Time in seconds after which to drop inactive connections

# HTTP Error codes
HTTP_ERR_MISSING_CLIENT_ID = 100
HTTP_ERR_MISSING_LOGIN_INFO = 102
HTTP_ERR_LOGIN_INCORRECT = 103
HTTP_ERR_CLIENT_IS_OFFLINE = 200
HTTP_ERR_CLIENT_IS_BUSY = 201
HTTP_ERR_CLIENT_DUPLICATE = 202
HTTP_ERR_CLIENT_NOT_RESPOND = 203
HTTP_ERR_CLIENT_FAIL_SEND = 204
HTTP_ERR_DOCUMENT_UNKNOWN = 205

# TCP Error codes
TCP_ERR_OK = 200
TCP_ERR_INVALID_CRYPTO_KEY = 501
TCP_ERR_INVALID_DATA_PACKET = 502
TCP_ERR_COMMAND_UNKNOWN = 503
TCP_ERR_FAIL_DECODE_DATA = 504
TCP_ERR_FAIL_ENCODE_DATA = 505
TCP_ERR_FAIL_INIT_CLIENT_ID = 510
TCP_ERR_DUPLICATE_CLIENT_ID = 511
TCP_ERR_RECEIVE_REPORT_ERROR = 520
TCP_ERR_CHECK_UPDATE_ERROR = 530

# Crypto dictionary for key generation
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
HARDCODED_KEYS = {
    1: 'D5F21NE-',   # Client ID=1
    2: 'D5F2aRD-',   # Client ID=2
    5: 'D5F2cNE-',   # Client ID=5
    6: 'D5F26NE-',   # Client ID=6
    9: 'D5F22NE-',   # Client ID=9
}

# Special key settings for ID=8
ID8_KEY = 'D028'
ID8_LEN = 4

# Line separator for response messages
LINE_SEPARATOR = '\r\n'

# Command response codes
RESPONSE_OK = '200'
RESPONSE_ERROR = 'ERROR' 
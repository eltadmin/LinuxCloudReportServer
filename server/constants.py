"""
Constants for the Report Server
"""

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

# Response format for INIT command
# 1 = Text key=value\r\nkey=value
# 2 = Text key1=value1 key2=value2
# 3 = Text as 1 with additional param (TIME=)
# 4 = Text as 2 with additional param (TIME=)
# 5 = Text key=value (without newlines)
# 6 = Text key=value key=value (without newlines)
# 7 = Binary Delphi TStringList.SaveToStream format (first byte = count, then strings separated by CRLF)
# 8 = Binary format with length-prefixed strings (4 bytes count, then each string prefixed with 4 bytes length)
DEFAULT_RESP_FORMAT = 1 
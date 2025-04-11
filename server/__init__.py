"""
Report Server Package
"""

from .server import ReportServer
from .tcp_server import TCPServer
from .http_server import HTTPServer
from .db import Database
from .crypto import DataCompressor
import logging
import sys

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[logging.StreamHandler(sys.stdout)]
)

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

__all__ = ['ReportServer', 'TCPServer', 'HTTPServer', 'Database', 'DataCompressor'] 
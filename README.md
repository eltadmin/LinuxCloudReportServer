# Linux Cloud Report Server

A Linux port of the CloudTcpServer that supports communication with the BosTcpClient through encrypted commands.

## Overview

This is a Python implementation of the report server that was originally written in Delphi for Windows. It implements the same protocol to maintain compatibility with existing clients.

The server uses:
- AES (Rijndael) encryption for secure communication
- Proper key generation that matches the Delphi implementation
- Full support for all required client commands
- Support for both TCP communication

## Features

- **Encryption**: Implements AES-CBC encryption with PKCS#7 padding, matching the Delphi DCPcrypt implementation
- **Key generation**: Correctly generates cryptographic keys using the format: `serverKey + dictEntryPart + hostFirstChars + hostLastChar`
- **Command handling**: Supports INIT, INFO, PING, VERS, ERRL, and DWNL commands
- **Logging**: Comprehensive logging with rotation for troubleshooting

## Requirements

- Python 3.8 or higher
- PyCryptodome for AES encryption
- Other dependencies listed in requirements.txt

## Installation

1. Clone the repository
2. Install dependencies: `pip install -r requirements.txt`

## Usage

Run the server:

```bash
python main.py --host 0.0.0.0 --port 8080 --log-level INFO
```

### Command-line arguments

- `--host`: Host IP to bind to (default: 0.0.0.0)
- `--port`: Port number to listen on (default: 8080)
- `--log-level`: Logging level (DEBUG, INFO, WARNING, ERROR, CRITICAL, default: INFO)

## Protocol Documentation

The server implements the following communication protocol:

### Initialization (INIT)

Client sends:
```
INIT ID=<client_id>
```

Server responds:
```
200-KEY=<server_key>
200 LEN=<key_length>
```

Where:
- `<server_key>`: Typically "D5F2"
- `<key_length>`: 1 for most client IDs, 2 for ID=9

The cryptographic key is generated as:
`serverKey + dictEntryPart + hostFirstChars + hostLastChar`

### Info Request (INFO)

Client sends:
```
INFO DATA=<encrypted_data>
```

Server responds:
```
200 OK
DATA=<encrypted_response>
```

The encrypted response contains essential client information including:
- TT=Test (validation field)
- ID (client ID)
- EX (expiry date)
- EN (enabled status)
- CD (creation date)
- CT (creation time)

### Other Commands

- `PING`: Server responds with "PONG"
- `VERS`: Returns version information
- `ERRL`: Handles error reports from clients
- `DWNL`: Handles file download requests

## Security

The server uses AES encryption with:
- MD5 hash of the key for the AES key
- Zero IV vector
- PKCS#7 padding

## Development

### Project Structure

- `main.py`: Entry point for the server
- `server/`: Core server implementation
  - `server.py`: Main server orchestration
  - `tcp_server.py`: TCP server implementation  
  - `crypto.py`: Encryption/decryption implementation
  - `key_manager.py`: Key generation and management
  - `message_handler.py`: Message formatting and parsing
  - `constants.py`: Protocol constants and configuration

### Testing

Run tests using pytest:

```bash
pytest
```

## License

Proprietary software - all rights reserved. 
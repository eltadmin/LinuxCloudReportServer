# Linux Cloud Report Server

A Linux implementation of the CloudTcpServer for TCP/IP communication with clients.

## Features

- Fully compatible with the original Delphi CloudTcpServer protocol
- TCP server for accepting client connections
- HTTP server for interfacing with web applications
- Cryptographic support for secure client communication
- Support for multiple server interfaces
- Logging and error handling
- Docker support for easy deployment

## Requirements

- Python 3.9+
- Dependencies listed in requirements.txt
- Docker (optional, for containerized deployment)

## Installation

### Using Docker (Recommended)

1. Clone the repository or extract the source code
2. Make sure Docker and Docker Compose are installed
3. Build and start the server:

```bash
docker-compose up -d
```

#### Docker Environment Variables

When using Docker, you can set the following environment variables in docker-compose.yml:

- `SERVER_SERIAL` - Override the hardware serial number detection
- `SERVER_KEY` - Use a specific registration key

Example:
```yaml
environment:
  - SERVER_SERIAL=my-server-id
  - SERVER_KEY=your-base64-encoded-key
```

If these variables are not set, the entrypoint script will attempt to detect the hardware serial and generate a key automatically.

### Manual Installation

1. Clone the repository or extract the source code
2. Install dependencies:

```bash
pip install -r requirements.txt
```

3. Make the start script executable:

```bash
chmod +x start_server.sh
```

4. Run the server:

```bash
./start_server.sh
```

## Configuration

The server configuration is stored in `config/server.ini`. The main configuration sections are:

- `COMMONSETTINGS`: General settings
- `REGISTRATION INFO`: Server registration information
- `SRV_X_COMMON`: Common settings for server interface X
- `SRV_X_HTTP`: HTTP settings for server interface X
- `SRV_X_TCP`: TCP settings for server interface X
- `SRV_X_AUTHSERVER`: Authentication server settings for interface X
- `SRV_X_HTTPLOGINS`: HTTP login credentials for interface X

## Key Generator

To generate a new server registration key:

```bash
python3 src/key_generator.py --output config/registration.ini
```

Then copy the registration info to your `server.ini` file:

```ini
[REGISTRATION INFO]
SERIAL NUMBER=your_serial_number
KEY=your_generated_key
```

For detailed instructions on registration key management, see [REGISTRATION_KEY_HOWTO.md](REGISTRATION_KEY_HOWTO.md).

To test your registration key:

```bash
python test_custom_key.py
```

Note: The registration key is tied to the hardware serial number of the server. When moving the server to different hardware, you will need to generate a new key.

## Protocol Description

The server implements the original CloudTcpServer protocol with the following commands:

- `INIT`: Initialize connection and crypto key
- `INFO`: Send client info and get authorization
- `PING`: Keep connection alive
- `GREQ`: Get pending request
- `SRSP`: Send response to request
- `VERS`: Check for software updates
- `DWNL`: Download update file
- `ERRL`: Log error message

## Directory Structure

```
LinuxCloudReportServer/
├── config/            # Configuration files
├── docker/            # Docker-related files
├── logs/              # Log files (created at runtime)
├── src/               # Source code
├── updates/           # Update files (for client updates)
├── docker-compose.yml # Docker Compose configuration
├── Dockerfile         # Docker build configuration
├── requirements.txt   # Python dependencies
├── README.md          # This file
└── start_server.sh    # Server startup script
```

## Troubleshooting

If you encounter any issues, check the log files in the `logs` directory. The main log file is `CloudReportLog.txt`.

Common issues:

- **Invalid registration key**: Make sure the registration key in `server.ini` is valid
- **Port conflicts**: Check if ports 8016 (TCP) and 8080 (HTTP) are available
- **Connection refused**: Make sure firewall settings allow connections to the configured ports

## License

This software is proprietary and confidential. 
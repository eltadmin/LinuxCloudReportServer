# Linux Cloud Report Server

This project is a Linux-compatible implementation of the original Delphi-based CloudTcpServer, providing identical functionality. It serves as a drop-in replacement designed to run in Docker containers on Linux systems.

## System Architecture

The system consists of three main components:

1. **Report Server** - A Python-based TCP and HTTP server that handles client connections and communication
2. **Web Interface** - A PHP 8 application (dreport) that provides a web UI for report management
3. **Database** - A MySQL database storing reports and client information

The entire solution is containerized with Docker for easy deployment and management.

## Components

### Report Server (Python)

The Report Server is implemented in Python and provides the following functionality:

- **TCP Server**: Handles client connections using the same protocol as the original Delphi server
- **HTTP Server**: Provides REST API endpoints for report generation and file downloads
- **Database Interface**: Connects to MySQL database for storing and retrieving reports

#### TCP Protocol

The TCP server implements a custom protocol with the following commands:

- `INIT` - Initialize connection and negotiate encryption key
- `INFO` - Exchange client information and authentication
- `PING` - Keep-alive mechanism
- `SRSP` - Handle client responses to server requests
- `GREQ` - Generate reports based on client parameters
- `VERS` - Check for updates and return file list
- `DWNL` - Download update files
- `ERRL` - Log client-side errors

The communication is secured using a custom encryption mechanism based on a shared cryptographic key negotiated during initialization.

#### HTTP API

The HTTP server provides the following endpoints:

- `/` - Server status information
- `/status` - Detailed status including client count and configuration
- `/report` - Generate reports based on parameters
- `/updates` - List available update files
- `/download/{filename}` - Download update files

All endpoints except for the root require basic authentication.

### Web Interface (PHP 8)

The web interface is located in the `dreport` directory and provides a user-friendly way to:

- View and manage reports
- Monitor client connections
- Configure server settings
- Manage update files

### Database

The database schema is defined in the `dreports(8).sql` file and contains tables for:

- Reports
- Clients
- User accounts
- Configuration settings

## Configuration

The server is configured using the `eboCloudReportServer.ini` file in the `config` directory. This file contains settings for:

- TCP and HTTP interfaces and ports
- Authentication settings
- Update folder location
- Logging configuration

Environment variables can be used to override configuration settings:

- `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` - Database connection
- `AUTH_SERVER_URL` - Authentication server URL

## Docker Deployment

The system is deployed using Docker Compose with three services:

1. `report_server` - The Python-based TCP/HTTP server
2. `web` - Apache/PHP web server for the dreport interface
3. `db` - MySQL database server

## Security

- Communication between clients and server is encrypted using a custom protocol
- HTTP endpoints require basic authentication
- Database credentials are managed through environment variables

## Logging and Monitoring

- The server uses rotating logs stored in the `logs` directory
- Log files are rotated when they reach 10MB, with up to 10 backup files
- Health checks are performed periodically and logged
- Old logs and update files are automatically cleaned up after 30 days

## Error Handling

The server includes robust error handling for:

- Database connectivity issues with automatic reconnection
- Network failures and timeouts
- Malformed client requests
- File system errors

## Interacting with Original Client

The BosTcpClient application can connect to this server exactly as it did to the original Delphi server. The protocol implementation ensures backward compatibility.

## Directory Structure

```
LinuxCloudReportServer/
├── config/
│   └── eboCloudReportServer.ini
├── dreport/
│   └── ... (PHP web interface)
├── server/
│   ├── __init__.py
│   ├── constants.py
│   ├── crypto.py
│   ├── db.py
│   ├── http_server.py
│   ├── server.py
│   └── tcp_server.py
├── .env
├── docker-compose.yml
├── Dockerfile.php
├── Dockerfile.server
├── main.py
├── php.ini
├── README.md
└── setup.py
```

## Starting the Server

To start the server, run:

```bash
docker-compose up -d
```

This will start all three containers and make the services available on their configured ports:

- TCP Server: Port 8016
- HTTP Server: Port 8080
- Web Interface: http://localhost:8015/dreport/

## Testing with Original Client

To test the server with the original BosTcpClient:

1. Ensure the server is running
2. Configure the client to connect to the server IP address on port 8016
3. The client should connect and function normally

## Troubleshooting

Common issues and solutions:

- **Database connection failures**: Check environment variables and network connectivity
- **HTTP authentication issues**: Verify credentials in eboCloudReportServer.ini
- **Client connection problems**: Ensure TCP port 8016 is accessible
- **File permission errors**: Check volume mounts in docker-compose.yml

## Maintenance

Regular maintenance tasks:

- Check logs for errors
- Monitor disk usage
- Back up the database regularly
- Update security certificates if used

For detailed implementation information, refer to the code comments in each component file.

## Using the Go TCP Server

The project now includes a Go-based TCP server implementation that exactly matches the Windows server's TCP protocol format. This is especially important for the INIT command response format, which needs to be compatible with the Delphi client.

### Why Use the Go TCP Server?

The Go TCP server implementation provides:

1. Better format compatibility with the Windows server
2. Improved performance and memory efficiency
3. Lower latency for client connections
4. Native handling of TCP connections without Python's overhead

### How to Use the Go TCP Server

#### With Docker Compose:

The `docker-compose.yml` file has been updated to include the Go TCP server. To use it:

```bash
# Start all services with the Go TCP server
docker-compose up -d
```

#### Standalone Usage:

You can also build and run the Go TCP server directly:

```bash
# On Linux
chmod +x start_go_server.sh
./start_go_server.sh

# On Windows
start_go_server.bat
```

#### Building Manually:

```bash
# Build the Go server
go build -o tcp_server go_tcp_server.go

# Run the server
./tcp_server
```

### Configuration

The Go TCP server reads the following environment variables:

- `TCP_HOST`: TCP interface to listen on (default: 0.0.0.0)
- `TCP_PORT`: TCP port to listen on (default: 8016)

### Disabling the Python TCP Server

When using the Go TCP server, you should disable the Python TCP server to avoid port conflicts. This is automatically handled in the Docker Compose setup, but if you're running the Python server manually, set the `DISABLE_TCP_SERVER` environment variable:

```bash
export DISABLE_TCP_SERVER=true
python -m main
``` 
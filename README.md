# EBO Cloud Report Server - Linux Version

This is a Linux-compatible version of the EBO Cloud Report Server. It provides the same functionality as the original Windows-only server, but can be run on any platform that supports Node.js or Docker.

## Features

- Full TCP server implementation compatible with original clients
- HTTP server for report generation
- Identical behavior to the original Windows server
- Docker support for easy deployment
- Configurable settings through INI file

## Requirements

- Node.js 14+ or Docker
- Access to the web interface (dreport)

## Configuration

The configuration is stored in `eboCloudReportServer.ini` in the same format as the original Windows server. The important settings are:

- `HTTP_Port`: HTTP server port (default: 8080)
- `TCP_Port`: TCP server port (default: 8016)
- `REST_URL`: URL to the REST API (default: http://10.150.40.8/dreport/api.php)

## Running Locally

### Using Node.js

1. Make sure Node.js is installed
2. Navigate to the `src` directory
3. Install dependencies:
   ```
   npm install
   ```
4. Start the server:
   ```
   npm start
   ```

### Using Docker

1. Make sure Docker and Docker Compose are installed
2. Build and start the container:
   ```
   docker-compose up -d
   ```

## Directory Structure

- `src/`: Source code for the server
- `logs/`: Log files
- `Updates/`: Update files for clients
- `dreport/`: Web interface (existing)

## Accessing the Server

- TCP server: `tcp://localhost:8016`
- HTTP server: `http://localhost:8080`
- Web interface: `http://localhost:8015/dreport/`

## Important Notes

1. The server preserves all functionalities of the original Windows server
2. The web interface is untouched and continues to work as before
3. Configuration is compatible with the original INI format
4. The REST API URL has been updated to 10.150.40.8 from 10.150.40.7
5. All logs are stored in the `logs` directory 
# Linux Cloud Report Server

A Linux-compatible version of the Cloud Report Server, rewritten in Python with modern async features. This server maintains full compatibility with the original Windows version while adding improved performance and reliability.

## Features

- Dual protocol support (HTTP and TCP)
- Async handling of multiple connections
- Report generation and management
- File updates and downloads
- Client authentication
- Trace logging
- Configuration via INI file
- Docker containerization

## Prerequisites

- Docker
- Docker Compose
- Git

## Directory Structure

```
LinuxCloudReportServer/
├── config/
│   └── eboCloudReportServer.ini
├── server/
│   ├── main.py
│   ├── server.py
│   ├── tcp_server.py
│   ├── http_server.py
│   └── db.py
├── dreport/           # PHP web interface
├── logs/              # Server logs
├── Updates/           # Update files
├── docker-compose.yml
├── Dockerfile.server
├── Dockerfile.php
└── requirements.txt
```

## Configuration

1. Copy `config/eboCloudReportServer.ini.example` to `config/eboCloudReportServer.ini`
2. Edit the configuration file to set:
   - HTTP and TCP server interfaces and ports
   - Authentication settings
   - Update folder path
   - Trace log settings

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd LinuxCloudReportServer
   ```

2. Configure environment variables:
   ```bash
   cp .env.example .env
   # Edit .env with your settings
   ```

3. Build and start the containers:
   ```bash
   docker-compose up -d
   ```

## Usage

### Web Interface

Access the web interface at: `http://localhost:8015/dreport/`

### TCP Client Connection

Connect to the TCP server at port 8016. Available commands:
- `INIT <client_id>` - Initialize connection
- `PING` - Keep-alive ping
- `VERS` - Get update versions
- `DWNL <filename>` - Download update file
- `GREQ <type> <params>` - Generate report

### HTTP API Endpoints

- `GET /` - Server status
- `GET /status` - Detailed server status
- `POST /report` - Generate report
- `GET /updates` - List available updates
- `GET /download/{filename}` - Download update file

## Development

1. Create a virtual environment:
   ```bash
   python -m venv venv
   source venv/bin/activate  # Linux
   # or
   .\venv\Scripts\activate  # Windows
   ```

2. Install dependencies:
   ```bash
   pip install -r requirements.txt
   ```

3. Run tests:
   ```bash
   python -m pytest tests/
   ```

## Docker Commands

- Build containers:
  ```bash
  docker-compose build
  ```

- Start services:
  ```bash
  docker-compose up -d
  ```

- View logs:
  ```bash
  docker-compose logs -f
  ```

- Stop services:
  ```bash
  docker-compose down
  ```

## License

This project maintains the same license as the original Windows version.

## Support

For support, please contact the system administrator or refer to the original documentation. 
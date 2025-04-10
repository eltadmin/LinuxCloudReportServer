# EBO Cloud Report Server - Linux Version

This is a Linux-compatible version of the EBO Cloud Report Server. It provides the same functionality as the original Windows-only server, but can be run on any platform that supports Node.js or Docker.

## Features

- Full TCP server implementation compatible with original clients
- HTTP server for report generation
- Identical behavior to the original Windows server
- Docker support for easy deployment
- Configurable settings through INI file
- MySQL database integration
- Web interface available on port 8015

## Requirements

- Node.js 14+ or Docker
- MySQL 8.0 database (included in Docker setup)
- PHP 8.0+ (for web interface)
- Access to the dreport web interface directory

## Configuration

The configuration is stored in `eboCloudReportServer.ini` in the same format as the original Windows server. The important settings are:

- `HTTP_Port`: HTTP server port (default: 8080)
- `TCP_Port`: TCP server port (default: 8016)
- `REST_URL`: URL to the REST API (default: http://10.150.40.8/dreport/api.php)

## Directory Structure

Before running the server, ensure you have the following directory structure:
```
LinuxCloudReportServer/
├── docker-compose.yml
├── Dockerfile
├── Dockerfile.mysql
├── Dockerfile.webinterface
├── dreport/               # Web interface files
├── dreports(8).sql        # MySQL database dump
├── import-database.sh
├── nginx.conf
├── README.md
├── src/                   # Server source code
│   ├── constants.js
│   ├── dbConnection.js
│   ├── eboCloudReportServer.ini
│   ├── package.json
│   ├── remoteConnection.js
│   ├── reportServer.js
│   ├── restApiInterface.js
│   └── server.js
├── start-fallback.sh
├── start-manual.sh
├── start.sh
├── stop.sh
└── supervisord.conf
```

Make sure the `dreport` directory contains all the web interface files.

## Database Setup

The system requires a MySQL database. The SQL dump file `dreports(8).sql` contains the database schema and data.

Default database credentials:
- Host: 127.0.0.1 (localhost)
- Database: dreports
- User: dreports
- Password: ftUk58_HoRs3sAzz8jk

When using Docker, these credentials can be modified in the docker-compose.yml file.

## Running the Server

### Preparing the Environment

First, make all the shell scripts executable:

```bash
chmod +x *.sh
```

### Option 1: Auto-detect with Fallback

The easiest way to start the server is to use the fallback script which will try Docker first and fall back to manual mode if needed:

```bash
./start-fallback.sh
```

### Option 2: Using Docker

1. Make sure Docker and Docker Compose are installed
2. Make sure the `dreports(8).sql` file is in the root directory
3. Make sure the `dreport` directory contains all the web interface files
4. Build and start the containers:
   ```bash
   ./start.sh
   ```
   
To stop the Docker containers:
```bash
./stop.sh
```

### Option 3: Manual Start (Without Docker)

If you don't want to use Docker or encounter issues with it:

1. Make sure Node.js and MySQL are installed
2. Import the database schema: 
   ```bash
   ./import-database.sh
   ```
3. Start the server manually:
   ```bash
   ./start-manual.sh
   ```
4. You'll need to set up the web interface separately in this case

## Troubleshooting

### Docker Build Issues

If you encounter issues building the Docker containers, such as:

```
npm error code 127
npm error path /app/node_modules/zlib
npm error command failed
npm error command sh -c node-waf clean || true; node-waf configure build
```

Try using the manual start method instead:

```bash
./start-manual.sh
```

### Database Connection Issues

If the server can't connect to the database, ensure the MySQL server is running and the database is properly imported:

1. Check the MySQL service:
   ```bash
   systemctl status mysql
   ```

2. Try manually connecting to the database:
   ```bash
   mysql -u dreports -pftUk58_HoRs3sAzz8jk dreports
   ```

### Web Interface Issues

If the web interface isn't working:

1. Verify that port 8015 is open and accessible
2. Check that the dreport directory is properly mounted in the container
3. Check the nginx logs for any errors:
   ```bash
   docker-compose logs -f web-interface
   ```

If you see error "host not found in upstream" in the logs, use the provided update script:
```bash
chmod +x update-configs.sh
./update-configs.sh
```

This will update the nginx configuration in the running container and reload nginx.

If you need to completely rebuild the web interface container:
```bash
chmod +x restart-web.sh
./restart-web.sh
```

## Accessing the Server

- TCP server: Port 8016 (configured in eboCloudReportServer.ini)
- HTTP server: Port 8080 (configured in eboCloudReportServer.ini)
- Web interface: http://localhost:8015/dreport/ (managed by nginx)
- MySQL database: Port 3306

## Important Notes

1. The server preserves all functionalities of the original Windows server
2. The web interface is now accessible through port 8015
3. Configuration is compatible with the original INI format
4. The REST API URL has been updated to 10.150.40.8 from 10.150.40.7
5. All logs are stored in the `logs` directory
6. Database is integrated with the system for consistent operation

# Linux Cloud Report Server

Cloud-based system for monitoring and reporting on EBO cash registers.

## System Overview

The system consists of three main components:
1. **MySQL Database** - Stores all the data and configuration
2. **Report Server** - Node.js application that handles TCP and HTTP connections
3. **Web Interface** - PHP-based web interface for managing and viewing reports

## Installation with Docker

The simplest way to deploy the entire system is using Docker and docker-compose.

### Prerequisites

- Docker Engine (19.03.0+)
- Docker Compose (v2.0.0+)
- Git (for cloning the repository)

### Steps to Install

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/ReportComServer.git
   cd ReportComServer
   ```

2. Build and start the services:
   ```bash
   cd LinuxCloudReportServer
   docker-compose up -d
   ```

3. The system will be available at:
   - Web Interface: http://localhost/dreport/
   - Report Server API: http://localhost:8080
   - TCP Server: localhost:8016

### Services

The docker-compose setup includes:

- **db** - MySQL database server on port 3306
- **report-server** - Node.js report server on ports 8080 (HTTP) and 8016 (TCP)
- **web-interface** - PHP web interface on port 80

## Manual Installation

If you prefer to install the components separately:

### Database Setup

1. Install MySQL 8.0:
   ```bash
   sudo apt install mysql-server
   ```

2. Import the database:
   ```bash
   mysql -u root -p < dreports.sql
   ```

3. Create the user:
   ```bash
   mysql -u root -p -e "CREATE USER 'dreports'@'localhost' IDENTIFIED BY 'ftUk58_HoRs3sAzz8jk'; GRANT ALL PRIVILEGES ON dreports.* TO 'dreports'@'localhost'; FLUSH PRIVILEGES;"
   ```

### Report Server Setup

1. Install Node.js and npm:
   ```bash
   sudo apt install nodejs npm
   ```

2. Install dependencies:
   ```bash
   cd src
   npm install
   ```

3. Configure the server:
   - Edit the `eboCloudReportServer.ini` file with your settings

4. Start the server:
   ```bash
   node server.js
   ```

### Web Interface Setup

1. Install dependencies:
   ```bash
   sudo apt install nginx php php-fpm php-mysql
   ```

2. Configure Nginx:
   - Copy `nginx.conf` to `/etc/nginx/sites-available/dreport`
   - Enable the site: `sudo ln -s /etc/nginx/sites-available/dreport /etc/nginx/sites-enabled/`

3. Restart Nginx:
   ```bash
   sudo systemctl restart nginx
   ```

## Troubleshooting

If you encounter issues with the web interface returning 404 errors:
1. Check the nginx configuration to ensure `/dreport/` is properly mapped
2. Verify the permissions of the web directory
3. Check the logs at `/var/log/nginx/error.log`

For database connection issues:
1. Verify the database credentials in both server and web configurations
2. Check if the MySQL service is running
3. Ensure the database and required tables exist

## License

This software is proprietary and confidential.
© 2024 EBO Systems

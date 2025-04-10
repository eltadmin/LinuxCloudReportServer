# EBO Cloud Report Server - Linux Version

This is a Linux-compatible version of the EBO Cloud Report Server. It provides the same functionality as the original Windows-only server, but can be run on any platform that supports Node.js or Docker.

## Features

- Full TCP server implementation compatible with original clients
- HTTP server for report generation
- Identical behavior to the original Windows server
- Docker support for easy deployment
- Configurable settings through INI file
- MySQL database integration

## Requirements

- Node.js 14+ or Docker
- MySQL 8.0 database (included in Docker setup)
- Access to the web interface (dreport)

## Configuration

The configuration is stored in `eboCloudReportServer.ini` in the same format as the original Windows server. The important settings are:

- `HTTP_Port`: HTTP server port (default: 8080)
- `TCP_Port`: TCP server port (default: 8016)
- `REST_URL`: URL to the REST API (default: http://10.150.40.8/dreport/api.php)

## Database Setup

The system requires a MySQL database. The SQL dump file `dreports(8).sql` contains the database schema and data.

Default database credentials:
- Host: 127.0.0.1 (localhost)
- Database: dreports
- User: dreports
- Password: ftUk58_HoRs3sAzz8jk

When using Docker, these credentials can be modified in the docker-compose.yml file.

## Running Locally

### Using Node.js

1. Make sure Node.js and MySQL are installed
2. Import the database schema: `mysql -u root -p < dreports(8).sql`
3. Navigate to the `src` directory
4. Install dependencies:
   ```
   npm install
   ```
5. Start the server:
   ```
   npm start
   ```

### Using Docker

1. Make sure Docker and Docker Compose are installed
2. Make sure the `dreports(8).sql` file is in the root directory
3. Build and start the containers:
   ```
   docker-compose up -d
   ```
   This will:
   - Start a MySQL container and automatically import the database
   - Start the report server connected to the database

## Directory Structure

- `src/`: Source code for the server
- `logs/`: Log files
- `Updates/`: Update files for clients
- `dreport/`: Web interface (existing)

## Accessing the Server

- TCP server: `tcp://localhost:8016`
- HTTP server: `http://localhost:8080`
- Web interface: `http://localhost:8015/dreport/`
- MySQL database: `mysql://localhost:3306/dreports`

## Important Notes

1. The server preserves all functionalities of the original Windows server
2. The web interface is untouched and continues to work as before
3. Configuration is compatible with the original INI format
4. The REST API URL has been updated to 10.150.40.8 from 10.150.40.7
5. All logs are stored in the `logs` directory
6. Database is integrated with the system for consistent operation 
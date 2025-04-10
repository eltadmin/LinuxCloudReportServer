# Cloud Report Server for Linux

This is a Linux-compatible implementation of the Cloud Report Server, rewritten in Node.js for cross-platform compatibility.

## Repository

The source code is available on GitHub: https://github.com/eltadmin/LinuxCloudReportServer

## Features

- HTTP Server for web requests
- TCP Server for client connections
- Client authentication and management
- Report generation and delivery
- Logging and diagnostics
- Update file management
- PostgreSQL database integration
- JWT-based authentication
- Prometheus monitoring
- Docker containerization

## Requirements

- Node.js 14.x or higher
- npm or yarn package manager
- PostgreSQL 12.x or higher (for production)
- Docker and Docker Compose (for containerized deployment)

## Installation

### Standard Installation

1. Clone the repository:
   ```
   git clone https://github.com/eltadmin/LinuxCloudReportServer.git
   cd LinuxCloudReportServer
   ```

2. Install dependencies:
   ```
   npm install
   ```

3. Set up environment variables:
   ```
   cp .env.example .env
   ```
   Edit the `.env` file to configure your environment.

4. Set up the database:
   ```
   npm run migrate
   ```

5. Start the server:
   ```
   npm start
   ```

### Docker Installation

1. Clone the repository:
   ```
   git clone https://github.com/eltadmin/LinuxCloudReportServer.git
   cd LinuxCloudReportServer
   ```

2. Configure environment:
   ```
   cp .env.example .env
   ```
   Edit the `.env` file to configure your environment.

3. Start with Docker Compose:
   ```
   docker-compose up -d
   ```

## Usage

### Starting the server

To start the server in production mode:

```
npm start
```

To start the server in development mode with automatic restart on file changes:

```
npm run dev
```

### API Endpoints

The server exposes the following HTTP API endpoints:

#### Authentication

- **POST /auth/login** - Login and get JWT token
- **GET /auth/me** - Get current user information
- **GET /auth/users** - List all users (admin only)
- **POST /auth/users** - Create a new user (admin only)

#### Reports

- **GET /api/health** - Server health check
- **GET /api/info** - Server information
- **GET /api/report?clientId=<id>&document=<doc>** - Generate a report
- **GET /api/clients** - List connected clients
- **GET /api/updates** - List available update files
- **GET /api/updates/:filename** - Download an update file

#### Monitoring

- **GET /metrics** - Prometheus metrics endpoint

### TCP Commands

The server handles the following TCP commands:

- **INIT** - Initialize client connection
- **INFO** - Get/set client information
- **PING** - Keep-alive check
- **SRSP** - Send report response
- **GREQ** - Get report request
- **VERS** - Version information
- **DWNL** - Download file
- **ERRL** - Error log

## Configuration

### Environment Variables

The server can be configured using environment variables or the `.env` file:

- **NODE_ENV** - Environment (development, production)
- **HTTP_PORT** - HTTP server port
- **TCP_PORT** - TCP server port
- **LOG_LEVEL** - Logging level
- **DB_HOST** - Database hostname
- **DB_PORT** - Database port
- **DB_NAME** - Database name
- **DB_USER** - Database username
- **DB_PASSWORD** - Database password
- **JWT_SECRET** - Secret for JWT tokens
- **JWT_EXPIRES_IN** - JWT token expiration time

### INI Configuration

Additional configuration is stored in the INI file at `config/server.ini`:

```ini
[http]
port=8080
interface=0.0.0.0

[tcp]
port=2909
interface=0.0.0.0

[server]
logPath=./logs
updatePath=./updates
traceLogEnabled=true
authServerUrl=http://localhost:8081/auth
```

## Monitoring

The server includes built-in monitoring with Prometheus. Metrics are exposed on the `/metrics` endpoint.

When deployed with Docker Compose, a Prometheus and Grafana stack is included for visualizing metrics.

Access Grafana at http://localhost:3000 (default credentials: admin/admin)

## Directory Structure

- **config/** - Configuration files
- **logs/** - Server log files
- **middleware/** - Express middleware
- **migrations/** - Database migrations
- **models/** - Data models
  - **db/** - Database models (Sequelize)
- **routes/** - HTTP API routes
- **utils/** - Utility functions
- **updates/** - Update files for clients
- **monitoring/** - Monitoring configuration

## License

See the LICENSE file for details. 
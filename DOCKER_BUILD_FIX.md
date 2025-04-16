# Docker Build Fix

We've identified and fixed issues with the Docker build configuration. The main problem was that the `Dockerfile.go` was referring to files with incorrect paths, and some required files were missing. Additionally, there was an issue with Go module dependencies not being properly resolved.

## Changes Made

1. Updated `Dockerfile.go` to use correct file paths:
   - Changed from `COPY LinuxCloudReportServer/go.mod .` to `COPY go.mod .`
   - Changed from `COPY LinuxCloudReportServer/go.sum* .` to `COPY go.sum* .`
   - Changed from `COPY LinuxCloudReportServer/go_tcp_server.go .` to `COPY go_tcp_server.go .`

2. Created `Dockerfile.http` for the HTTP server component.

3. Added `requirements.txt` file for Python dependencies.

4. Created necessary directories:
   - `templates` - For web template files
   - `static` - For static files like CSS, JS, etc.
   - `uploads` - For file uploads
   - `logs` - For application logs

5. Added setup scripts to automate environment preparation:
   - `setup_docker_environment.sh` - For Linux/Unix environments
   - `setup_docker_environment.ps1` - For Windows environments

6. Added scripts to fix Go module dependencies:
   - `fix_go_modules.sh` - For Linux/Unix environments
   - `fix_go_modules.ps1` - For Windows environments

## Prerequisites

Before building the Docker containers, ensure you have the following:

1. Docker and Docker Compose installed
2. The project structure (described at the end of this document)
3. All necessary files in their correct locations

## Automated Setup and Fix

For your convenience, we've included setup scripts that will prepare the environment automatically:

### On Linux/Unix:

```bash
# Make the scripts executable
chmod +x setup_docker_environment.sh fix_go_modules.sh

# First run the setup script
./setup_docker_environment.sh

# Fix Go module dependencies (important for successful build)
./fix_go_modules.sh

# Now build the containers
docker-compose build --no-cache
```

### On Windows:

```powershell
# You might need to set execution policy
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass

# Run the setup script
.\setup_docker_environment.ps1

# Fix Go module dependencies (important for successful build)
.\fix_go_modules.ps1

# Now build the containers
docker-compose build --no-cache
```

These scripts will create the necessary directories and files required for the Docker build to succeed, and fix the Go module dependencies issue.

## Known Issue: Go Module Dependencies

If you encounter the following error during build:
```
missing go.sum entry for module providing package github.com/mattn/go-sqlite3; to add:
go mod download github.com/mattn/go-sqlite3
```

Run the provided fix script:
- On Linux/Unix: `./fix_go_modules.sh`
- On Windows: `.\fix_go_modules.ps1`

This script will:
1. Create a temporary Docker environment
2. Properly download and initialize all Go modules
3. Update the go.sum file with the correct dependencies
4. Clean up temporary files

After running this script, the Docker build should succeed.

## How to Build the Containers

To build the Go TCP server container:

```bash
# Navigate to the LinuxCloudReportServer directory
cd /path/to/LinuxCloudReportServer

# Build only the Go TCP server
docker-compose build --no-cache go_tcp_server

# Or build all services
docker-compose build --no-cache
```

### Build on Windows PowerShell

```powershell
# Navigate to the LinuxCloudReportServer directory
cd \path\to\LinuxCloudReportServer

# Build the Go TCP server
docker-compose build --no-cache go_tcp_server

# Or build all services
docker-compose build --no-cache
```

## Running the Services

Once built, you can run the services with:

```bash
# Start all services
docker-compose up -d

# Or start only the Go TCP server
docker-compose up -d go_tcp_server
```

## Troubleshooting

If you encounter any issues:

1. Make sure Docker is installed and running
2. Verify that the file paths in the Dockerfiles match your project structure
3. Check if the required files exist in your project directory
4. Check Docker logs for more details:
   ```bash
   docker-compose logs go_tcp_server
   ```

5. Common error: "not found" errors during build
   - This typically means a file or directory is missing
   - Run one of the setup scripts to create all necessary directories and files
   - Or manually create the directories: templates, static, uploads, logs
   - Ensure requirements.txt exists in the project root

6. Go module errors:
   - If you see errors related to go.sum or missing Go modules
   - Run the fix_go_modules script for your platform
   - This will properly initialize and download all required Go modules

## Project Structure

The project should have the following structure for the Docker build to work correctly:

```
LinuxCloudReportServer/
├── go_tcp_server.go
├── go.mod
├── go.sum
├── Dockerfile.go
├── Dockerfile.http
├── docker-compose.yml
├── docker-compose.go.yml
├── requirements.txt     # Important: Must contain all Python dependencies
├── main.py
├── setup_docker_environment.sh   # Setup script for Linux/Unix
├── setup_docker_environment.ps1  # Setup script for Windows
├── fix_go_modules.sh             # Go modules fix script for Linux/Unix
├── fix_go_modules.ps1            # Go modules fix script for Windows
├── server/
│   ├── server.py
│   ├── crypto.py
│   ├── db.py
│   ├── constants.py
│   ├── message.py
│   ├── http_server.py
│   ├── __init__.py
│   └── main.py
├── config/
│   └── eboCloudReportServer.ini
├── logs/              # Directory for logs
├── uploads/           # Directory for uploads
├── static/            # Directory for static files
└── templates/         # Directory for template files
```

Make sure all files are in the correct locations.

## Required Files

The `requirements.txt` file should contain these dependencies:
```
fastapi>=0.68.0
uvicorn>=0.15.0
python-multipart>=0.0.5
aiofiles>=0.7.0
python-dotenv>=0.19.0
asyncio>=3.4.3
aiohttp>=3.8.1
mysqlclient>=2.0.3
SQLAlchemy>=1.4.23
pydantic>=1.8.2
cryptography>=3.4.7
PyYAML>=5.4.1
aiomysql>=0.1.1
pycryptodome>=3.15.0
psycopg2-binary>=2.9.1
jinja2>=3.0.1
``` 
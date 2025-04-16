# Docker Build Fix

We've identified and fixed issues with the Docker build configuration. The main problem was that the `Dockerfile.go` was referring to files with incorrect paths.

## Changes Made

1. Updated `Dockerfile.go` to use correct file paths:
   - Changed from `COPY LinuxCloudReportServer/go.mod .` to `COPY go.mod .`
   - Changed from `COPY LinuxCloudReportServer/go.sum* .` to `COPY go.sum* .`
   - Changed from `COPY LinuxCloudReportServer/go_tcp_server.go .` to `COPY go_tcp_server.go .`

2. Created `Dockerfile.http` for the HTTP server component.

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
├── requirements.txt
├── main.py
├── server/
│   └── server.py
└── config/
```

Make sure all files are in the correct locations. 
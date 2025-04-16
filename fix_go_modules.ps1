# This script fixes Go module dependencies for the Docker build on Windows
# It creates a temporary Dockerfile that just initializes the Go modules

Write-Host "Creating temporary Docker environment to fix Go modules..." -ForegroundColor Green

# Create a temporary Dockerfile for Go module initialization
@"
FROM golang:1.21-alpine

WORKDIR /app

# Install required packages
RUN apk add --no-cache gcc musl-dev

# Copy go.mod and go.sum
COPY go.mod .
COPY go.sum* .

# Download dependencies and update go.sum
RUN go mod download
RUN go get github.com/mattn/go-sqlite3
RUN go mod tidy

# Copy the updated go.sum back to the host
CMD cp go.sum /mnt/go.sum
"@ | Out-File -FilePath "Dockerfile.tmp" -Encoding utf8

Write-Host "Building temporary Docker image for Go module initialization..." -ForegroundColor Cyan
docker build -t go-mod-init -f Dockerfile.tmp .

Write-Host "Running container to extract updated go.sum..." -ForegroundColor Cyan
$currentDir = (Get-Location).Path
docker run --rm -v ${currentDir}:/mnt go-mod-init

Write-Host "Cleaning up temporary files..." -ForegroundColor Cyan
Remove-Item -Path "Dockerfile.tmp" -Force

Write-Host "`nGo modules have been fixed. You can now build the main Docker image:" -ForegroundColor Green
Write-Host "  docker-compose build --no-cache" -ForegroundColor White
Write-Host "Done!" -ForegroundColor Green 
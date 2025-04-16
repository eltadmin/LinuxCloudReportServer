#!/bin/bash

# This script fixes Go module dependencies for the Docker build
# It creates a temporary Dockerfile that just initializes the Go modules

echo "Creating temporary Docker environment to fix Go modules..."

# Create a temporary Dockerfile for Go module initialization
cat > Dockerfile.tmp << EOL
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
EOL

echo "Building temporary Docker image for Go module initialization..."
docker build -t go-mod-init -f Dockerfile.tmp .

echo "Running container to extract updated go.sum..."
docker run --rm -v $(pwd):/mnt go-mod-init

echo "Cleaning up temporary files..."
rm Dockerfile.tmp

echo "Go modules have been fixed. You can now build the main Docker image:"
echo "  docker-compose build --no-cache"
echo "Done!" 
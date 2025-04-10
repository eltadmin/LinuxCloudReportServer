#!/bin/bash

# Check if Docker and Docker Compose are installed
if ! command -v docker-compose &> /dev/null; then
    echo "Error: Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

echo "Stopping EBO Cloud Report Server..."

# Stop Docker containers
docker-compose down

if [ $? -ne 0 ]; then
    echo "Error: Failed to stop containers."
    exit 1
fi

echo "Containers stopped successfully!" 
#!/bin/bash

# Check if Docker and Docker Compose are installed
if ! command -v docker &> /dev/null; then
    echo "Error: Docker is not installed. Please install Docker first."
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "Error: Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Check if dreports(8).sql exists
if [ ! -f "dreports(8).sql" ]; then
    echo "Error: dreports(8).sql not found in the current directory."
    echo "Please make sure the SQL dump file is in the same directory as this script."
    exit 1
fi

echo "Starting EBO Cloud Report Server..."
echo "This will start the database and server containers."
echo "The first startup may take several minutes while the database is being imported."

# Start Docker containers
docker-compose up -d

if [ $? -ne 0 ]; then
    echo "Error: Failed to start containers."
    exit 1
fi

echo ""
echo "Containers started successfully!"
echo ""
echo "Access points:"
echo "- TCP server: tcp://localhost:8016"
echo "- HTTP server: http://localhost:8080"
echo "- MySQL database: mysql://localhost:3306/dreports"
echo ""
echo "To stop the server, run: ./stop.sh"
echo "To view server logs, run: docker-compose logs -f report-server" 
#!/bin/bash

echo "EBO Cloud Report Server Starter with Fallback"
echo "============================================="

# Check if dreport directory exists
if [ ! -d "dreport" ]; then
    echo "Warning: dreport directory not found. This directory is required for the web interface."
    echo "The web interface may not work correctly without it."
    read -p "Do you want to continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Check if Docker is installed
if command -v docker &> /dev/null && command -v docker-compose &> /dev/null; then
    echo "Docker is available, attempting to start with Docker..."
    
    # Try to start with Docker
    docker-compose up -d
    
    # Check the result
    if [ $? -eq 0 ]; then
        echo ""
        echo "Docker containers started successfully!"
        echo ""
        echo "Access points:"
        echo "- TCP server port: 8016"
        echo "- HTTP server port: 8080"
        echo "- Web interface: http://localhost:8015/dreport/"
        echo "- MySQL database port: 3306"
        echo ""
        echo "To stop the server, run: ./stop.sh"
        echo "To view server logs, run: docker-compose logs -f report-server"
        exit 0
    else
        echo ""
        echo "Docker failed to start containers."
        echo "Falling back to manual mode..."
        echo ""
    fi
else
    echo "Docker is not available. Falling back to manual mode..."
fi

# Check if MySQL is running locally
if command -v mysql &> /dev/null; then
    echo "Checking MySQL connection..."
    mysql -u dreports -pftUk58_HoRs3sAzz8jk -h 127.0.0.1 -e "SELECT 1" dreports &> /dev/null
    if [ $? -ne 0 ]; then
        echo "Warning: Cannot connect to MySQL database with the dreports user."
        echo "You may need to import the database first with: ./import-database.sh"
        read -p "Do you want to continue anyway? (y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    else
        echo "MySQL connection successful!"
    fi
else
    echo "Warning: MySQL client not found."
    echo "Make sure MySQL server is running and the database is imported."
fi

# Start manually
echo "Starting in manual mode..."
echo "Note: In manual mode, the web interface needs to be configured separately."
echo "You will need to set up a web server to serve the dreport directory on port 8015."
./start-manual.sh 
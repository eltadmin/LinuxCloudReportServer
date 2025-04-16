#!/bin/bash

# Setup script for Docker environment
echo "Setting up Docker environment for Linux Cloud Report Server..."

# Create necessary directories
echo "Creating required directories..."
mkdir -p logs uploads static templates

# Check if requirements.txt exists
if [ ! -f "requirements.txt" ]; then
    echo "Creating requirements.txt..."
    cat > requirements.txt << EOL
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
EOL
    echo "requirements.txt created."
else
    echo "requirements.txt already exists."
fi

# Check if config directory exists
if [ ! -d "config" ]; then
    echo "Creating config directory and sample configuration..."
    mkdir -p config
    
    # Create a sample config file if it doesn't exist
    if [ ! -f "config/eboCloudReportServer.ini" ]; then
        cat > config/eboCloudReportServer.ini << EOL
[server]
host = 0.0.0.0
port = 8000
debug = true

[database]
host = db
port = 5432
user = report
password = reportpass
name = reports

[logging]
level = DEBUG
file = logs/server.log
EOL
        echo "Sample config created."
    fi
else
    echo "Config directory exists."
fi

# Fix Go module dependencies
echo "Checking and fixing Go module dependencies..."
if command -v go &> /dev/null; then
    echo "Go is installed, updating go.sum..."
    go mod tidy
    echo "Go modules updated."
else
    echo "Go is not installed locally. The go.sum will be updated during Docker build."
fi

# Check Docker and Docker Compose installation
echo "Checking Docker installation..."
if ! command -v docker &> /dev/null; then
    echo "Docker not found. Please install Docker first."
    exit 1
else
    echo "Docker is installed."
fi

if ! command -v docker-compose &> /dev/null; then
    echo "Docker Compose not found. Please install Docker Compose first."
    exit 1
else
    echo "Docker Compose is installed."
fi

# Final steps
echo "Environment setup complete. You can now build and run Docker containers:"
echo "  docker-compose build --no-cache"
echo "  docker-compose up -d"
echo "Done!" 
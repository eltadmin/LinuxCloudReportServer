#!/bin/bash
# Script to run a local test of the Linux Cloud Report Server

set -e  # Exit on error

echo "Linux Cloud Report Server - Local Test Setup"
echo "----------------------------------------"

# Check for Python 3.9+
python_version=$(python3 --version | cut -d' ' -f2)
python_major=$(echo $python_version | cut -d'.' -f1)
python_minor=$(echo $python_version | cut -d'.' -f2)

if [ "$python_major" -lt 3 ] || [ "$python_major" -eq 3 -a "$python_minor" -lt 9 ]; then
    echo "Error: Python 3.9 or higher is required (found $python_version)"
    exit 1
fi

echo "✅ Python version $python_version"

# Create virtual environment if it doesn't exist
if [ ! -d "venv" ]; then
    echo "Creating virtual environment..."
    python3 -m venv venv
    echo "✅ Virtual environment created"
else
    echo "✅ Virtual environment exists"
fi

# Activate virtual environment
echo "Activating virtual environment..."
source venv/bin/activate
echo "✅ Virtual environment activated"

# Install dependencies
echo "Installing dependencies..."
pip install -e .
echo "✅ Dependencies installed"

# Create necessary directories
echo "Creating necessary directories..."
mkdir -p logs Updates
echo "✅ Directories created"

# Check for configuration file
if [ ! -f "config/eboCloudReportServer.ini" ]; then
    echo "⚠️  Configuration file not found at config/eboCloudReportServer.ini"
    echo "Creating a sample configuration file..."
    
    mkdir -p config
    cat > config/eboCloudReportServer.ini << EOF
[COMMONSETTINGS]
CommInterfaceCount=1

[REGISTRATION INFO]
SERIAL NUMBER=987654321
KEY=TestKey12345

[SRV_1_COMMON]
TraceLogEnabled=1
UpdateFolder=Updates

[SRV_1_HTTP]
HTTP_IPInterface=0.0.0.0
HTTP_Port=8080

[SRV_1_TCP]
TCP_IPInterface=0.0.0.0
TCP_Port=8016

[SRV_1_AUTHSERVER]
REST_URL=http://localhost:8015/dreport/api.php

[SRV_1_HTTPLOGINS]
user=pass$123
admin=admin123
EOF
    echo "✅ Sample configuration file created"
else
    echo "✅ Configuration file exists"
fi

# Set up environment variables
export DB_HOST=localhost
export DB_USER=dreports
export DB_PASSWORD=dreports
export DB_NAME=dreports
export AUTH_SERVER_URL=http://localhost:8015/dreport/api.php
export PYTHONPATH=$(pwd)

echo ""
echo "----------------------------------------"
echo "Environment is set up. You can run the server with:"
echo "python main.py"
echo ""
echo "Or test the TCP client connection with:"
echo "python test_client_connection.py --host localhost --port 8016 -v"
echo ""
echo "Note: This test setup doesn't include a MySQL database."
echo "For full functionality, you need to start the Docker containers with docker-compose."
echo "----------------------------------------"

# Ask if user wants to run the server
read -p "Do you want to run the server now? (y/n) " run_server

if [ "$run_server" = "y" ] || [ "$run_server" = "Y" ]; then
    echo "Starting the server..."
    echo "Press Ctrl+C to stop the server"
    python main.py
else
    echo "You can run the server later with 'python main.py'"
fi 
#!/bin/bash

# Setup script for DReport integration
# This script sets up and initializes the DReport system

echo "Setting up DReport integration with LinuxCloudReportServer..."

# Check if we're in the correct directory
if [ ! -d "config" ]; then
  echo "Error: Please run this script from the LinuxCloudReportServer root directory"
  exit 1
fi

# Create necessary directories if they don't exist
mkdir -p config/dreport

# Check if dreport directory exists at the correct level
if [ ! -d "../dreport" ]; then
  echo "Error: dreport directory not found at the expected location (../dreport)"
  echo "Please ensure the dreport directory is placed in the correct location"
  exit 1
fi

# Ensure MySQL client is installed (for local testing)
if ! command -v mysql &> /dev/null; then
  echo "MySQL client not found, installing..."
  if command -v apt-get &> /dev/null; then
    sudo apt-get update
    sudo apt-get install -y mysql-client
  elif command -v yum &> /dev/null; then
    sudo yum install -y mysql
  elif command -v apk &> /dev/null; then
    apk add --no-cache mysql-client
  else
    echo "Warning: Cannot install MySQL client automatically"
    echo "Please install MySQL client manually if needed for local development"
  fi
fi

# Install dependencies
echo "Installing Node.js dependencies..."
npm install

# Start the containers in detached mode
echo "Starting docker containers..."
docker-compose up -d

# Wait for services to start
echo "Waiting for services to start (30 seconds)..."
sleep 30

# Initialize dreport
echo "Initializing DReport system..."
npm run init-dreport

echo "Setup complete! DReport is now available at: http://localhost:8015/dreport/index.php"
echo "You can restart the services at any time with: docker-compose restart" 
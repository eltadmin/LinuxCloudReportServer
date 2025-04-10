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
mkdir -p config/dreport logs updates

# Check if dreport directory exists at the correct level
if [ ! -d "../dreport" ]; then
  echo "Error: dreport directory not found at the expected location (../dreport)"
  echo "Please ensure the dreport directory is placed in the correct location"
  exit 1
fi

# Add curl to Dockerfile if it's missing
if ! grep -q "curl" Dockerfile; then
  echo "Adding curl to Dockerfile for healthchecks..."
  sed -i 's/supervisor/supervisor \\\n    curl/g' Dockerfile
fi

# Install curl for the troubleshooting script
if [ -x "$(command -v apt-get)" ]; then
  echo "Installing curl with apt-get..."
  sudo apt-get update
  sudo apt-get install -y curl
elif [ -x "$(command -v yum)" ]; then
  echo "Installing curl with yum..."
  sudo yum install -y curl
elif [ -x "$(command -v apk)" ]; then
  echo "Installing curl with apk..."
  apk add --no-cache curl
fi

# Start the containers in detached mode
echo "Starting docker containers..."
docker-compose down
docker-compose up -d

# Wait for services to start
echo "Waiting for services to start (60 seconds)..."
sleep 60

# Run the troubleshooting script
echo "Running troubleshooting checks..."
chmod +x scripts/troubleshoot.sh
./scripts/troubleshoot.sh

# Initialize dreport
echo "Initializing DReport system..."
curl -X GET http://localhost:8080/init-dreport

echo "Setup complete! DReport is now available at: http://localhost:8015/dreport/index.php"
echo "You can restart the services at any time with: docker-compose restart"
echo "If you encounter issues, run the troubleshooting script: ./scripts/troubleshoot.sh" 
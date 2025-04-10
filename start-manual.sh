#!/bin/bash

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "Error: Node.js is not installed. Please install Node.js first."
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "Error: npm is not installed. Please install npm first."
    exit 1
fi

echo "Starting EBO Cloud Report Server (manual mode)..."

# Create necessary directories
mkdir -p logs
mkdir -p Updates

# Navigate to src directory
cd src

# Check if packages are installed
if [ ! -d "node_modules" ]; then
    echo "Installing dependencies..."
    npm install
fi

# Start the server
echo "Starting server..."
node server.js

# Check for errors
if [ $? -ne 0 ]; then
    echo "Error: Failed to start server."
    exit 1
fi 
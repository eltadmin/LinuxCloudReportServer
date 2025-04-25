#!/bin/bash
# Setup environment for Cloud Report Server

# Get script directory
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

echo "Setting up environment for Cloud Report Server..."

# Create virtual environment (optional)
if command -v python3 -m venv &> /dev/null; then
    echo "Creating virtual environment..."
    python3 -m venv venv
    source venv/bin/activate
    PYTHON="venv/bin/python3"
    PIP="venv/bin/pip"
else
    PYTHON="python3"
    PIP="pip3"
fi

# Install dependencies
echo "Installing dependencies..."
$PIP install -r "${SCRIPT_DIR}/requirements.txt"

# Create necessary directories
echo "Creating necessary directories..."
mkdir -p "${SCRIPT_DIR}/logs"
mkdir -p "${SCRIPT_DIR}/updates"
chmod 777 "${SCRIPT_DIR}/logs"
chmod 777 "${SCRIPT_DIR}/updates"

echo "Environment setup complete!"
echo "You can now run the server with ./start_server.sh" 
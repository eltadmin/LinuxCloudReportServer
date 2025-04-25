#!/bin/bash
# Start the Cloud Report Server

# Get script directory
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

# Set environment variables
export PYTHONPATH="${SCRIPT_DIR}"

# Set configuration file path
export CONFIG_FILE="${SCRIPT_DIR}/config/server.ini"

# Check for virtual environment
if [ -d "${SCRIPT_DIR}/venv" ]; then
    echo "Using virtual environment..."
    PYTHON="${SCRIPT_DIR}/venv/bin/python3"
else
    echo "Using system Python..."
    PYTHON="python3"
    
    # Check if required packages are installed
    if ! $PYTHON -c "import Crypto" &> /dev/null; then
        echo "Error: Required package 'Crypto' is not installed."
        echo "Please run './setup_environment.sh' first or install dependencies with:"
        echo "pip install -r requirements.txt"
        exit 1
    fi
fi

# Create log and updates directory if they don't exist
mkdir -p "${SCRIPT_DIR}/logs"
mkdir -p "${SCRIPT_DIR}/updates"

echo "Starting Cloud Report Server..."
echo "Log files will be in: ${SCRIPT_DIR}/logs"

# Start the server
$PYTHON "${SCRIPT_DIR}/src/server.py" 
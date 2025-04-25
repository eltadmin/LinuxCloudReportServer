#!/bin/bash
# Start the Cloud Report Server

# Set environment variables
export PYTHONPATH="$(dirname "$(readlink -f "$0")")"

# Get script directory
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

# Set configuration file path
CONFIG_FILE="${SCRIPT_DIR}/config/server.ini"

# Start the server
python3 "${SCRIPT_DIR}/src/server.py" 
#!/bin/bash
# entrypoint.sh - Docker container entrypoint script for Cloud Report Server

set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting Cloud Report Server container...${NC}"

# Create required directories if they don't exist
echo "Checking required directories..."
mkdir -p /app/logs
mkdir -p /app/config
mkdir -p /app/updates

# Set proper permissions
echo "Setting permissions..."
chmod -R 755 /app
chmod -R 777 /app/logs
chmod -R 777 /app/updates

# Check for server configuration
if [ ! -f "/app/config/server.ini" ]; then
    echo -e "${YELLOW}Warning: server.ini not found - creating default configuration${NC}"
    
    cat > /app/config/server.ini << EOF
[Server]
Host = 0.0.0.0
Port = 8016
WebInterfacePort = 8080
LogLevel = INFO
MaxConnections = 100

[Security]
EnableEncryption = True
KeysFile = /app/config/keys.json
EnableIPFilter = False
AllowedIPs = 127.0.0.1,192.168.0.0/24

[Updates]
UpdatesFolder = /app/updates
CheckForUpdates = True
EOF
    
    echo "Default server.ini created"
fi

# Check for keys file
if [ ! -f "/app/config/keys.json" ]; then
    echo -e "${YELLOW}Warning: keys.json not found - creating empty keys file${NC}"
    echo '{"server_keys": {}, "client_keys": {}}' > /app/config/keys.json
    echo "Empty keys.json created - you will need to generate encryption keys"
fi

# Run diagnostics script
echo -e "${GREEN}Running system diagnostics...${NC}"
python /app/debug_server.py
DIAG_RESULT=$?

if [ $DIAG_RESULT -ne 0 ]; then
    echo -e "${RED}Diagnostics failed! Check logs for details.${NC}"
    echo "You can still continue with server startup by setting IGNORE_DIAGNOSTICS=1"
    
    if [ "${IGNORE_DIAGNOSTICS}" != "1" ]; then
        echo -e "${RED}Exiting due to failed diagnostics${NC}"
        exit 1
    else
        echo -e "${YELLOW}Ignoring diagnostics failures and continuing with startup${NC}"
    fi
fi

# Start the server
echo -e "${GREEN}Starting Cloud Report Server...${NC}"
echo "=================================================================================="
echo "Access web interface at http://localhost:8080 (or the configured port)"
echo "Server is listening for client connections on port 8016 (or the configured port)"
echo "Logs are available in /app/logs directory"
echo "=================================================================================="

# Execute the main server script
exec python /app/server.py 
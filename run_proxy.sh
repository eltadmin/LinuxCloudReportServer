#!/bin/bash
# Script to run the TCP Proxy for traffic analysis

echo "Starting TCP Proxy for traffic analysis"
echo "======================================"

# Save current directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Default values
TARGET_HOST=${1:-"10.150.40.8"}
TARGET_PORT=${2:-"8016"}
LISTEN_PORT=8017

echo "Stopping existing proxy container..."
docker-compose stop proxy_server

echo "Starting proxy_server..."
export TARGET_HOST=$TARGET_HOST
export TARGET_PORT=$TARGET_PORT
docker-compose up -d proxy_server

echo
echo "TCP Proxy is running and listening on port $LISTEN_PORT"
echo "Forwarding traffic to $TARGET_HOST:$TARGET_PORT"
echo
echo "Instructions:"
echo "1. Configure your Delphi client to connect to port $LISTEN_PORT instead of $TARGET_PORT"
echo "2. The proxy will record all communication between client and server"
echo "3. Check proxy_log.txt for detailed logs"
echo
echo "Viewing logs (press Ctrl+C to stop):"
docker-compose logs -f proxy_server 
#!/bin/bash

# Build the Go TCP server
echo "Building Go TCP server..."
go build -o tcp_server go_tcp_server.go

# Check if build was successful
if [ $? -ne 0 ]; then
    echo "Failed to build Go TCP server"
    exit 1
fi

# Start the Go TCP server
echo "Starting Go TCP server..."
./tcp_server 
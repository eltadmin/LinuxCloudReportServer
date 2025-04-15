#!/bin/bash
# Script to restart the server after making changes

echo "Stopping Docker containers..."
docker-compose down

echo "Building and starting Docker containers..."
docker-compose up -d

echo "Waiting 5 seconds for services to start..."
sleep 5

echo "Showing logs from server..."
docker-compose logs -f report_server 
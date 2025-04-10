#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting EBO Cloud Report Server consolidated fix...${NC}"

# Check for Docker and Docker Compose
echo -e "${YELLOW}Checking for Docker...${NC}"
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Docker is not installed. Please install Docker first.${NC}"
    exit 1
fi

echo -e "${YELLOW}Checking for Docker Compose...${NC}"
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Docker Compose is not installed. Please install Docker Compose first.${NC}"
    exit 1
fi

# Ensure we're in the correct directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
cd "$SCRIPT_DIR"

echo -e "${YELLOW}Creating configuration files...${NC}"

# Create directories if they don't exist
mkdir -p logs Updates

# Check if the SQL file exists
if [ ! -f "../dreports(8).sql" ]; then
    echo -e "${RED}Database SQL file not found: ../dreports(8).sql${NC}"
    echo -e "${RED}Please ensure the file exists and is in the correct location.${NC}"
    exit 1
fi

# Make sure all scripts are executable
chmod +x *.sh

echo -e "${YELLOW}Building and starting Docker containers...${NC}"
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Wait for containers to start
echo -e "${YELLOW}Waiting for services to start...${NC}"
sleep 10

# Check if the web interface is accessible
echo -e "${YELLOW}Checking web interface...${NC}"
if curl -s -f http://localhost/dreport/ > /dev/null; then
    echo -e "${GREEN}Web interface is accessible at http://localhost/dreport/${NC}"
else
    echo -e "${RED}Web interface is not accessible. Check the logs for errors.${NC}"
fi

# Check if the report server API is accessible
echo -e "${YELLOW}Checking report server API...${NC}"
if curl -s -f http://localhost:8080/health > /dev/null; then
    echo -e "${GREEN}Report server API is accessible at http://localhost:8080${NC}"
else
    echo -e "${RED}Report server API is not accessible. Check the logs for errors.${NC}"
fi

echo -e "${GREEN}Configuration complete!${NC}"
echo -e "${GREEN}Services:${NC}"
echo -e "${GREEN}- Web Interface: http://localhost/dreport/${NC}"
echo -e "${GREEN}- Report Server API: http://localhost:8080${NC}"
echo -e "${GREEN}- TCP Server: localhost:8016${NC}"
echo -e "${GREEN}- MySQL Database: localhost:3306${NC}"

echo -e "${YELLOW}To view logs:${NC}"
echo -e "${YELLOW}docker-compose logs -f${NC}"

echo -e "${YELLOW}To stop the services:${NC}"
echo -e "${YELLOW}docker-compose down${NC}"

echo -e "${GREEN}Setup completed successfully!${NC}" 
#!/bin/bash

echo "Restarting web interface container..."

docker-compose stop web-interface
docker-compose rm -f web-interface
docker-compose up -d web-interface

echo "Web interface restarted. Check logs with: docker-compose logs -f web-interface" 
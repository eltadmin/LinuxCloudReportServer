#!/bin/bash

echo "Updating configuration files in containers..."

# Копиране на nginx.conf в уеб интерфейс контейнера
docker cp nginx.conf ebo-web-interface:/etc/nginx/http.d/default.conf

# Рестартиране на nginx в контейнера
docker exec ebo-web-interface nginx -s reload

echo "Configuration files updated successfully."
echo "Nginx has been reloaded with the new configuration." 
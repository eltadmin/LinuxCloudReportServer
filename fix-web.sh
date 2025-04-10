#!/bin/bash

echo "Fixing web interface issues..."

# Проверка дали има файлове в dreport директорията
if [ ! -d "dreport" ] || [ -z "$(ls -A dreport 2>/dev/null)" ]; then
    echo "Error: dreport directory is missing or empty!"
    echo "Please ensure that you have the dreport files."
    exit 1
fi

# Създаване на updated-nginx.conf с по-прости настройки
cat > updated-nginx.conf << 'EOL'
server {
    listen 80;
    server_name localhost;

    # Основен път
    root /var/www/html;
    index index.php index.html;

    # Директно обслужване на /dreport
    location = /dreport {
        return 301 /;
    }

    # Обслужване на /dreport/ директорията като root
    location /dreport/ {
        alias /var/www/html/;
        try_files $uri $uri/ /index.php?$query_string;
        
        # PHP за dreport
        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $request_filename;
        }
    }

    # Стандартен PHP handler
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        
        # База данни
        fastcgi_param DB_HOST db;
        fastcgi_param DB_USER dreports;
        fastcgi_param DB_PASSWORD ftUk58_HoRs3sAzz8jk;
        fastcgi_param DB_NAME dreports;
    }

    # Основен уеб интерфейс
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # API прокси
    location /api/ {
        resolver 127.0.0.11;
        set $upstream_server http://ebo-cloud-report-server:8080/;
        proxy_pass $upstream_server;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Логове
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;
}
EOL

echo "Created simpler nginx configuration"

# Копиране на новата конфигурация в контейнера
docker cp updated-nginx.conf ebo-web-interface:/etc/nginx/http.d/default.conf

# Проверка на съдържанието на уеб контейнера
echo "Checking container content:"
docker exec ebo-web-interface ls -la /var/www/html

# Рестартиране на nginx в контейнера
docker exec ebo-web-interface nginx -s reload

# Изчакване на презареждането
sleep 2

# Проверка на логовете
echo "Checking nginx errors:"
docker exec ebo-web-interface cat /var/log/nginx/error.log

echo "Fix complete. Try accessing http://localhost:8015/dreport/ now." 
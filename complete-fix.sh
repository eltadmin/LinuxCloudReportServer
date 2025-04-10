#!/bin/bash

echo "╔════════════════════════════════════════════════════════════╗"
echo "║ Complete Fix for EBO Report Server Web Interface            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo

# Стъпка 1: Поправяне на nginx конфигурацията
echo "Step 1: Fixing nginx configuration..."
cat > updated-nginx.conf << 'EOL'
server {
    listen 80;
    server_name localhost;

    # Root directory
    root /var/www/html;
    index index.php index.html;

    # PHP files handling
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        
        # Database environment variables
        fastcgi_param DB_HOST db;
        fastcgi_param DB_USER dreports;
        fastcgi_param DB_PASSWORD ftUk58_HoRs3sAzz8jk;
        fastcgi_param DB_NAME dreports;
    }

    # Main interface
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Special handling for /dreport path
    location = /dreport {
        return 301 /;
    }

    # Allow access to /dreport/ path
    location /dreport/ {
        root /var/www/;
        try_files $uri $uri/ /dreport/index.php?$query_string;
    }

    # API proxy
    location /api/ {
        resolver 127.0.0.11;
        set $upstream_server http://ebo-cloud-report-server:8080/;
        proxy_pass $upstream_server;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Logs
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;
}
EOL

# Копиране на конфигурацията
docker cp updated-nginx.conf ebo-web-interface:/etc/nginx/http.d/default.conf
echo "✓ Nginx configuration updated"

# Стъпка 2: Инсталиране на Slim framework
echo
echo "Step 2: Installing Slim framework..."
docker exec ebo-web-interface sh -c "cd /tmp && \
    curl -sL https://github.com/slimphp/Slim/archive/2.6.3.tar.gz -o slim.tar.gz && \
    tar -xzf slim.tar.gz && \
    mkdir -p /var/www/html/protected/slim && \
    cp -r Slim-2.6.3/Slim/* /var/www/html/protected/slim/ && \
    chown -R www-data:www-data /var/www/html/protected/slim && \
    rm -rf Slim-2.6.3 slim.tar.gz"

# Стъпка 3: Инсталиране на PHP разширения
echo
echo "Step 3: Installing required PHP extensions..."
docker exec ebo-web-interface docker-php-ext-install -j$(nproc) pdo pdo_mysql mysqli

# Стъпка 4: Повторно копиране на файловете
echo
echo "Step 4: Re-copying files to container..."
if [ -d "dreport" ]; then
    # Създаване на tarball
    tar -czf dreport.tar.gz dreport/
    
    # Копиране на tarball в контейнера
    docker cp dreport.tar.gz ebo-web-interface:/tmp/
    
    # Екстракция в контейнера
    docker exec ebo-web-interface sh -c "tar -xzf /tmp/dreport.tar.gz -C /var/www/ && \
        cp -R /var/www/dreport/* /var/www/html/ && \
        chown -R www-data:www-data /var/www/html/ && \
        rm /tmp/dreport.tar.gz"
    
    # Изтриване на локалния tarball
    rm dreport.tar.gz
    
    echo "✓ Files copied successfully"
else
    echo "✗ dreport directory not found"
fi

# Стъпка 5: Настройка на правата
echo
echo "Step 5: Setting correct permissions..."
docker exec ebo-web-interface sh -c "chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html"

# Стъпка 6: Рестартиране на уеб сървъра
echo
echo "Step 6: Restarting web server..."
docker exec ebo-web-interface nginx -s reload
docker exec ebo-web-interface supervisorctl restart php-fpm

# Стъпка 7: Проверка на инсталацията
echo
echo "Step 7: Verifying installation..."
echo "Checking if Slim.php exists:"
docker exec ebo-web-interface ls -la /var/www/html/protected/slim/Slim.php 2>/dev/null && \
    echo "✓ Slim.php exists" || echo "✗ Slim.php not found"

echo
echo "Creating test PHP file..."
docker exec ebo-web-interface sh -c "echo '<?php phpinfo(); ?>' > /var/www/html/phpinfo.php"

echo
echo "╔════════════════════════════════════════════════════════════╗"
echo "║ Fix completed!                                              ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo
echo "Please try accessing the following URLs:"
echo "- Web Interface: http://localhost:8015/dreport/"
echo "- API Endpoint: http://localhost:8015/dreport/api.php"
echo "- PHP Info: http://localhost:8015/phpinfo.php"
echo
echo "If you still have issues, check the logs with:"
echo "docker-compose logs -f web-interface" 
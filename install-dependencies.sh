#!/bin/bash

echo "Installing necessary dependencies for dreport web interface..."

# Инсталиране на необходимите PHP разширения
echo "Installing required PHP extensions..."
docker exec ebo-web-interface docker-php-ext-install pdo pdo_mysql mysqli

# Инсталиране на Composer (PHP package manager)
echo "Installing Composer..."
docker exec ebo-web-interface sh -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"

# Инсталиране на Slim Framework
echo "Installing Slim Framework..."
docker exec ebo-web-interface sh -c "cd /tmp && \
    curl -sL https://github.com/slimphp/Slim/archive/2.6.3.tar.gz -o slim.tar.gz && \
    tar -xzf slim.tar.gz && \
    mkdir -p /var/www/html/protected/slim && \
    cp -r Slim-2.6.3/Slim/* /var/www/html/protected/slim/ && \
    rm -rf Slim-2.6.3 slim.tar.gz"

# Настройка на правата
echo "Setting correct permissions..."
docker exec ebo-web-interface sh -c "chown -R www-data:www-data /var/www/html"

# Създаване на тестов PHP файл
echo "Creating test PHP file..."
docker exec ebo-web-interface sh -c "echo '<?php phpinfo(); ?>' > /var/www/html/phpinfo.php"

# Проверка на настройките на PHP
echo "PHP configuration:"
docker exec ebo-web-interface php -i | grep "include_path"

echo "Dependencies installed successfully."
echo "You can check PHP configuration at http://localhost:8015/phpinfo.php"
echo "Try accessing http://localhost:8015/dreport/api.php now." 
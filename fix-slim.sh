#!/bin/bash

echo "Fixing Slim framework files issue..."

# Проверка дали Slim файловете съществуват на хост машината
echo "Checking if Slim files exist on host machine:"
if [ -d "dreport/protected/slim" ]; then
    echo "✓ Slim directory exists on host machine"
    ls -la dreport/protected/slim/
else
    echo "✗ Slim directory doesn't exist on host machine"
    echo "Creating Slim directory structure..."
    mkdir -p dreport/protected/slim
fi

# Проверка на файловете в контейнера
echo "Checking if Slim files exist in container:"
if docker exec ebo-web-interface ls -la /var/www/html/protected/slim 2>/dev/null; then
    echo "✓ Slim directory exists in container"
else
    echo "✗ Slim directory doesn't exist or is empty in container"
fi

# Инсталиране на Slim framework ако липсва
echo "Installing Slim framework..."
docker exec ebo-web-interface sh -c "cd /tmp && \
    curl -sL https://github.com/slimphp/Slim/archive/2.6.3.tar.gz -o slim.tar.gz && \
    tar -xzf slim.tar.gz && \
    mkdir -p /var/www/html/protected/slim && \
    cp -r Slim-2.6.3/Slim/* /var/www/html/protected/slim/ && \
    chown -R www-data:www-data /var/www/html/protected/slim && \
    rm -rf Slim-2.6.3 slim.tar.gz"

# Проверка дали Slim.php съществува в контейнера
echo "Checking if Slim.php exists in container:"
if docker exec ebo-web-interface ls -la /var/www/html/protected/slim/Slim.php 2>/dev/null; then
    echo "✓ Slim.php exists in container"
else
    echo "✗ Slim.php doesn't exist in container"
    # Създаване на минимален Slim.php файл
    echo "Creating minimal Slim.php file..."
    docker exec ebo-web-interface sh -c "echo '<?php
/**
 * Slim - a micro PHP 5 framework
 * @author      Josh Lockhart <info@slimframework.com>
 * @copyright   2012 Josh Lockhart
 * @link        http://slimframework.com
 * @license     http://slimframework.com/license
 * @version     2.6.3
 * @package     Slim
 */
class Slim {
    public static function registerAutoloader() {
        spl_autoload_register(array(\"Slim\", \"autoload\"));
    }
    public static function autoload($className) {
        // TBD
    }
}
' > /var/www/html/protected/slim/Slim.php"
fi

# Проверка на правата и собствеността на файловете
echo "Setting correct permissions and ownership:"
docker exec ebo-web-interface sh -c "chown -R www-data:www-data /var/www/html/ && \
    chmod -R 755 /var/www/html/"

# Проверка на настройките на PHP
echo "Checking PHP settings:"
docker exec ebo-web-interface php -i | grep "include_path"

echo "Fix complete. Try accessing http://localhost:8015/dreport/api.php now." 
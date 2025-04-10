#!/bin/bash

echo "EBO Web Interface Diagnostic Checklist"
echo "======================================"
echo

echo "1. Проверка на контейнерите:"
docker ps | grep ebo
echo

echo "2. Проверка на съдържанието на dreport папката в host машината:"
ls -la dreport
echo

echo "3. Проверка на съдържанието във web контейнера:"
docker exec ebo-web-interface ls -la /var/www/html
echo

echo "4. Проверка на nginx конфигурацията:"
docker exec ebo-web-interface cat /etc/nginx/http.d/default.conf
echo

echo "5. Проверка дали има файлове в html директорията на контейнера:"
docker exec ebo-web-interface find /var/www/html -type f | wc -l
echo "Брой файлове: "
echo

echo "6. Проверка на nginx error log:"
docker exec ebo-web-interface cat /var/log/nginx/error.log
echo

echo "7. Проверка на nginx access log:"
docker exec ebo-web-interface cat /var/log/nginx/access.log
echo

echo "8. Проверка на мрежовите връзки:"
docker exec ebo-web-interface netstat -tulpn | grep nginx
echo

echo "Диагностика завършена!" 
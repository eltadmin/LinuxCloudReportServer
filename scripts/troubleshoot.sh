#!/bin/bash

# Troubleshooting script for dreport integration

echo "Performing troubleshooting for dreport integration..."

# Check if containers are running
echo -e "\n=== Checking container status ==="
docker ps | grep -e cloud-report-server -e dreport-db

# Check Apache configuration
echo -e "\n=== Checking Apache configuration ==="
docker exec cloud-report-server cat /etc/apache2/conf.d/dreport.conf

# Check if Apache is running
echo -e "\n=== Checking if Apache is running ==="
docker exec cloud-report-server ps aux | grep httpd

# Check if dreport files exist
echo -e "\n=== Checking if dreport files exist ==="
docker exec cloud-report-server ls -la /var/www/html/dreport

# Check permissions on dreport directory
echo -e "\n=== Checking permissions on dreport directory ==="
docker exec cloud-report-server ls -la /var/www/html

# Check Apache error logs
echo -e "\n=== Checking Apache error logs ==="
docker exec cloud-report-server tail -n 20 /var/log/apache2/error.log

# Check database connectivity
echo -e "\n=== Checking MySQL connectivity ==="
docker exec cloud-report-server php -r "try { new PDO('mysql:host=mysql;dbname=dreports', 'dreports', 'ftUk58_HoRs3sAzz8jk'); echo 'Connection successful!'; } catch(PDOException \$e) { echo 'Connection failed: ' . \$e->getMessage(); }"

# Check port mapping
echo -e "\n=== Checking port mapping ==="
docker port cloud-report-server

# Check if port 8015 is reachable
echo -e "\n=== Checking if port 8015 is reachable ==="
docker exec cloud-report-server curl -I http://localhost:8015/dreport/index.php -s | head -n 1

# Check if supervisord is running
echo -e "\n=== Checking supervisord status ==="
docker exec cloud-report-server ps aux | grep supervisord

# Read supervisor logs
echo -e "\n=== Checking supervisor logs ==="
docker exec cloud-report-server cat /var/log/supervisor/setup-stdout.log

echo -e "\n=== Fixing permissions (if needed) ==="
docker exec cloud-report-server chown -R apache:apache /var/www/html/dreport
docker exec cloud-report-server chmod -R 755 /var/www/html/dreport

echo -e "\n=== Troubleshooting complete ==="
echo "If issues persist, try the following:"
echo "1. Restart the containers: docker-compose restart"
echo "2. Rebuild the containers: docker-compose down && docker-compose up -d --build"
echo "3. Check firewall settings to ensure port 8015 is open"
echo "4. Check that the dreport files are correctly placed in the LinuxCloudReportServer/dreport directory"
echo "5. Try accessing the site at: http://localhost:8015/dreport/index.php" 
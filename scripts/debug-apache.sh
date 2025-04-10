#!/bin/bash

# Debug script for Apache issues in the LinuxCloudReportServer container

echo "Collecting debug information for Apache..."

# Check container status
echo -e "\n=== Container Status ==="
docker ps | grep cloud-report-server

# Check if Apache is running
echo -e "\n=== Apache Process ==="
docker exec cloud-report-server ps aux | grep httpd

# Check Apache configuration
echo -e "\n=== Apache Configuration ==="
docker exec cloud-report-server cat /etc/apache2/conf.d/dreport.conf

# Check Apache main configuration
echo -e "\n=== Apache Main Configuration ==="
docker exec cloud-report-server cat /etc/apache2/httpd.conf | grep -E "Listen|ServerName|LoadModule|Directory"

# Check if port 8015 is actually being listened on
echo -e "\n=== Network Ports Inside Container ==="
docker exec cloud-report-server netstat -tulpn | grep LISTEN

# Check Apache error logs
echo -e "\n=== Apache Error Logs ==="
docker exec cloud-report-server cat /var/log/apache2/error.log

# Check supervisor logs
echo -e "\n=== Supervisor Logs ==="
docker exec cloud-report-server cat /var/log/supervisor/apache-stderr.log

# Check setup script logs
echo -e "\n=== Setup Script Logs ==="
docker exec cloud-report-server cat /var/log/supervisor/setup-stdout.log

# Check PHP version
echo -e "\n=== PHP Version ==="
docker exec cloud-report-server php -v

# Check if PHP is working with Apache
echo -e "\n=== PHP Info File Test ==="
docker exec cloud-report-server sh -c "echo '<?php phpinfo(); ?>' > /var/www/html/info.php"
docker exec cloud-report-server curl -s http://localhost:8015/info.php | grep -i "PHP Version"

# Check dreport directory contents
echo -e "\n=== Contents of dreport directory ==="
docker exec cloud-report-server ls -la /var/www/html/dreport

# Check permissions of www directory
echo -e "\n=== Permissions of Web Directory ==="
docker exec cloud-report-server ls -la /var/www/html

# Fix common issues
echo -e "\n=== Applying Fixes ==="
echo "1. Ensuring Apache can run..."
docker exec cloud-report-server mkdir -p /run/apache2
docker exec cloud-report-server chown -R apache:apache /run/apache2

echo "2. Ensuring proper permissions..."
docker exec cloud-report-server chown -R apache:apache /var/www/html
docker exec cloud-report-server chmod -R 755 /var/www/html

echo "3. Restarting Apache..."
docker exec cloud-report-server supervisorctl restart apache

echo -e "\n=== Debug Complete ==="
echo "If issues persist, check the above debug information for errors."
echo "You may need to rebuild the container: docker-compose down && docker-compose up -d --build" 
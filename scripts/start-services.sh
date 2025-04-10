#!/bin/sh

# Print diagnostic information
echo "Starting container services..."
echo "Container environment:"
echo "- NODE_ENV: $NODE_ENV"
echo "- HTTP_PORT: $HTTP_PORT" 
echo "- TCP_PORT: $TCP_PORT"

# Make sure directory structure exists
mkdir -p /var/www/html/dreport
mkdir -p /var/log/apache2
mkdir -p /run/apache2
mkdir -p /app/logs
mkdir -p /app/updates

# Set proper permissions
echo "Setting permissions..."
chown -R apache:apache /var/www/html
chmod -R 755 /var/www/html

# Create a test file to verify Apache access
echo '<?php echo "Apache for dreport is working!"; ?>' > /var/www/html/dreport/status.php

# Verify Apache configuration
echo "Verifying Apache configuration..."
httpd -t

# Create Apache lock file directory if missing
if [ ! -d /run/apache2 ]; then
  mkdir -p /run/apache2
  chown apache:apache /run/apache2
fi

# Start supervisord
echo "Starting all services via supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf 
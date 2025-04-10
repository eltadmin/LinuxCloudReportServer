#!/bin/bash

echo "Directly copying dreport files to container..."

# Check if dreport directory exists
if [ ! -d "dreport" ]; then
    echo "Error: dreport directory not found."
    exit 1
fi

# Check if dreport directory is empty
if [ -z "$(ls -A dreport 2>/dev/null)" ]; then
    echo "Error: dreport directory is empty."
    exit 1
fi

# Create tarball of dreport directory
echo "Creating tarball of dreport directory..."
tar -czf dreport.tar.gz dreport/

# Copy tarball to container
echo "Copying tarball to container..."
docker cp dreport.tar.gz ebo-web-interface:/tmp/

# Extract tarball in container
echo "Extracting tarball in container..."
docker exec ebo-web-interface sh -c "rm -rf /var/www/html/* && tar -xzf /tmp/dreport.tar.gz -C /var/www/ && cp -r /var/www/dreport/* /var/www/html/ && chown -R www-data:www-data /var/www/html"

# Remove tarball
echo "Cleaning up..."
rm dreport.tar.gz
docker exec ebo-web-interface rm /tmp/dreport.tar.gz

# Check the content of /var/www/html
echo "Checking content of /var/www/html:"
docker exec ebo-web-interface ls -la /var/www/html

echo "Direct copy complete. Try accessing http://localhost:8015/dreport/ now." 
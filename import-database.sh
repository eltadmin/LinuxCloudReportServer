#!/bin/bash

# Check if MySQL client is installed
if ! command -v mysql &> /dev/null; then
    echo "Error: MySQL client not found. Please install MySQL client."
    exit 1
fi

echo "This script will import the dreports database."
echo "Please provide MySQL credentials:"

# Get MySQL root password
read -sp "MySQL root password: " MYSQL_ROOT_PASSWORD
echo ""

# Create database and user
echo "Creating database and user..."
mysql -u root -p${MYSQL_ROOT_PASSWORD} << EOF
CREATE DATABASE IF NOT EXISTS dreports CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE USER IF NOT EXISTS 'dreports'@'localhost' IDENTIFIED BY 'ftUk58_HoRs3sAzz8jk';
GRANT ALL PRIVILEGES ON dreports.* TO 'dreports'@'localhost';
FLUSH PRIVILEGES;
EOF

if [ $? -ne 0 ]; then
    echo "Error: Failed to create database or user."
    exit 1
fi

# Import SQL dump
echo "Importing database from dreports(8).sql..."
echo "This may take a while, please be patient..."
mysql -u root -p${MYSQL_ROOT_PASSWORD} dreports < dreports\(8\).sql

if [ $? -ne 0 ]; then
    echo "Error: Failed to import database."
    exit 1
fi

echo "Database import completed successfully!"
echo "You can now start the server." 
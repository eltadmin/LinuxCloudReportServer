FROM php:8.0-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip pdo_mysql mysqli

# Enable Apache modules
RUN a2enmod rewrite

# Configure Apache
RUN sed -i 's/80/8015/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy PHP configuration
COPY php.ini /usr/local/etc/php/

# The dreport directory will be mounted as a volume

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 8015 
FROM php:8.0-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-install zip pdo_mysql mysqli

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Enable Apache modules
RUN a2enmod rewrite

# Configure Apache
RUN sed -i 's/80/8015/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Install Slim framework
RUN mkdir -p /var/www/html/dreport/protected/slim \
    && cd /var/www/html/dreport/protected/slim \
    && composer require slim/slim:^3.0

# Copy PHP configuration
COPY php.ini /usr/local/etc/php/

# Set timezone
RUN echo "date.timezone = UTC" > /usr/local/etc/php/conf.d/timezone.ini

# The dreport directory will be mounted as a volume

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 8015 
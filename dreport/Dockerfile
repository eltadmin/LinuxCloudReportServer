FROM php:8.2-apache

# Инсталиране на необходимите PHP разширения
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql zip

# Конфигуриране на Apache
RUN a2enmod rewrite
RUN a2enmod ssl

# Копиране на SSL сертификати (ще бъдат генерирани при стартиране)
RUN mkdir -p /etc/apache2/ssl

# Копиране на конфигурационните файлове
COPY .htaccess /var/www/html/.htaccess
COPY dreport.conf /etc/apache2/sites-available/000-default.conf

# Копиране на кода на уебсайта
COPY . /var/www/html/

# Задаване на правилните права
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Създаване на директория за логове
RUN mkdir -p /var/log/apache2 \
    && chown -R www-data:www-data /var/log/apache2

# Експозиране на портовете
EXPOSE 8010
EXPOSE 8015

# Стартиране на Apache
CMD ["apache2-foreground"] 
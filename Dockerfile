FROM php:8.2-apache

# Install required packages and Node.js
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    gnupg \
    lsb-release \
    net-tools \
    supervisor \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    git

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Configure PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
    mysqli \
    pdo \
    pdo_mysql \
    gd \
    zip \
    intl

# Enable Apache modules
RUN a2enmod rewrite expires deflate headers

WORKDIR /app

# Install Node.js dependencies
COPY package*.json ./
RUN npm install --omit=dev

# Copy application files
COPY server.js reportServerUnit.js ./
COPY utils/ utils/
COPY routes/ routes/
COPY models/ models/
COPY middleware/ middleware/
COPY migrations/ migrations/
COPY monitoring/ monitoring/
COPY config/ config/
COPY __tests__/ __tests__/

# Create necessary directories
RUN mkdir -p logs updates

# Create Apache virtual host for dreport
RUN echo '<VirtualHost *:8015>\n\
    ServerName localhost\n\
    DocumentRoot /var/www/html/dreport\n\
    ErrorLog ${APACHE_LOG_DIR}/dreport-error.log\n\
    CustomLog ${APACHE_LOG_DIR}/dreport-access.log combined\n\
    <Directory /var/www/html/dreport>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/dreport.conf

# Enable the dreport site and configure ports
RUN echo "Listen 8015" >> /etc/apache2/ports.conf && \
    a2ensite dreport

# Create test PHP files
RUN mkdir -p /var/www/html/dreport && \
    echo '<?php phpinfo(); ?>' > /var/www/html/dreport/phpinfo.php && \
    echo '<?php echo "Apache is working correctly for dreport!"; ?>' > /var/www/html/dreport/test.php

# Expose ports (HTTP, TCP, and Apache PHP)
EXPOSE 8080 8016 8015

# Set up supervisor to manage multiple processes
RUN mkdir -p /etc/supervisor/conf.d /var/log/supervisor
RUN echo '[supervisord]\n\
nodaemon=true\n\
logfile=/var/log/supervisor/supervisord.log\n\
logfile_maxbytes=50MB\n\
logfile_backups=10\n\
loglevel=info\n\
pidfile=/run/supervisord.pid\n\
\n\
[program:node]\n\
command=node server.js\n\
directory=/app\n\
autostart=true\n\
autorestart=true\n\
stderr_logfile=/var/log/supervisor/node-stderr.log\n\
stdout_logfile=/var/log/supervisor/node-stdout.log\n\
\n\
[program:apache2]\n\
command=apache2-foreground\n\
autostart=true\n\
autorestart=true\n\
stderr_logfile=/var/log/supervisor/apache-stderr.log\n\
stdout_logfile=/var/log/supervisor/apache-stdout.log\n\
\n\
[program:setup-permissions]\n\
command=sh -c "chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html"\n\
autostart=true\n\
autorestart=false\n\
startsecs=0\n\
priority=1\n\
stderr_logfile=/var/log/supervisor/setup-stderr.log\n\
stdout_logfile=/var/log/supervisor/setup-stdout.log' > /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Command to run supervisor which will manage both Node.js and Apache
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 
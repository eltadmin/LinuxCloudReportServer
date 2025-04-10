FROM node:18-alpine

# Install PHP, Apache, and required extensions
RUN apk add --no-cache \
    php8 \
    php8-fpm \
    php8-mysqli \
    php8-json \
    php8-openssl \
    php8-curl \
    php8-pdo \
    php8-pdo_mysql \
    php8-session \
    php8-mbstring \
    php8-xml \
    php8-tokenizer \
    apache2 \
    php8-apache2 \
    curl \
    supervisor \
    net-tools

WORKDIR /app

# Install Node.js dependencies
COPY package*.json ./
RUN npm install --omit=dev

# Copy application files (excluding dreport)
COPY server.js reportServerUnit.js ./
COPY utils/ utils/
COPY routes/ routes/
COPY models/ models/
COPY middleware/ middleware/
COPY migrations/ migrations/
COPY monitoring/ monitoring/
COPY config/ config/
COPY __tests__/ __tests__/
COPY scripts/ scripts/

# Create necessary directories
RUN mkdir -p logs updates /var/log/apache2 /run/apache2

# Configure Apache for dreport
RUN mkdir -p /var/www/html/dreport

# Setup Apache configuration with a direct path to dreport
RUN echo 'Listen 8015\n\
ServerName localhost\n\
<VirtualHost *:8015>\n\
    DocumentRoot /var/www/html/dreport\n\
    ErrorLog /var/log/apache2/error.log\n\
    CustomLog /var/log/apache2/access.log combined\n\
    <Directory /var/www/html/dreport>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/conf.d/dreport.conf

# Enable necessary Apache modules
RUN sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf && \
    sed -i 's/#LoadModule deflate_module/LoadModule deflate_module/' /etc/apache2/httpd.conf && \
    sed -i 's/#LoadModule expires_module/LoadModule expires_module/' /etc/apache2/httpd.conf && \
    echo 'AddDefaultCharset UTF-8' >> /etc/apache2/httpd.conf && \
    echo 'ServerName localhost' >> /etc/apache2/httpd.conf

# Expose ports (HTTP, TCP, and Apache PHP)
EXPOSE 8080 8016 8015

# Create test PHP files to verify Apache is working
RUN echo '<?php phpinfo(); ?>' > /var/www/html/test.php
RUN echo '<?php echo "Apache is working for dreport!"; ?>' > /var/www/html/dreport/test.php

# Set up supervisor to manage multiple processes
RUN mkdir -p /etc/supervisor/conf.d /var/log/supervisor
RUN echo '[supervisord]\n\
nodaemon=true\n\
\n\
[program:node]\n\
command=node server.js\n\
directory=/app\n\
autostart=true\n\
autorestart=true\n\
stderr_logfile=/var/log/supervisor/node-stderr.log\n\
stdout_logfile=/var/log/supervisor/node-stdout.log\n\
\n\
[program:apache]\n\
command=/usr/sbin/httpd -DFOREGROUND -e debug\n\
autostart=true\n\
autorestart=true\n\
stderr_logfile=/var/log/supervisor/apache-stderr.log\n\
stdout_logfile=/var/log/supervisor/apache-stdout.log\n\
\n\
[program:setup-permissions]\n\
command=sh -c "chown -R apache:apache /var/www/html && chmod -R 755 /var/www/html"\n\
autostart=true\n\
autorestart=false\n\
startsecs=0\n\
startretries=1\n\
stderr_logfile=/var/log/supervisor/setup-stderr.log\n\
stdout_logfile=/var/log/supervisor/setup-stdout.log' > /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chown -R apache:apache /var/www/html

# Make startup script executable
RUN chmod +x /app/scripts/start-services.sh

# Command to run supervisor which will manage both Node.js and Apache
CMD ["/app/scripts/start-services.sh"] 
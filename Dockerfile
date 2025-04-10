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
RUN mkdir -p logs updates /var/log/apache2 /run/apache2 /var/www/html/dreport

# Create Apache configuration to directly run PHP files from dreport
RUN echo 'Listen 8015' >> /etc/apache2/httpd.conf && \
    echo '<VirtualHost *:8015>\n\
    ServerName localhost\n\
    DocumentRoot /var/www/html/dreport\n\
    ErrorLog /var/log/apache2/dreport-error.log\n\
    CustomLog /var/log/apache2/dreport-access.log combined\n\
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
    echo 'ServerName localhost' >> /etc/apache2/httpd.conf && \
    echo 'AddHandler application/x-httpd-php .php' >> /etc/apache2/httpd.conf

# Create test PHP files to quickly verify if Apache is working
RUN echo '<?php phpinfo(); ?>' > /var/www/html/dreport/phpinfo.php && \
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
pidfile=/var/run/supervisord.pid\n\
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
command=/usr/sbin/httpd -DFOREGROUND\n\
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
priority=1\n\
stderr_logfile=/var/log/supervisor/setup-stderr.log\n\
stdout_logfile=/var/log/supervisor/setup-stdout.log' > /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chown -R apache:apache /var/www/html && chmod -R 755 /var/www/html

# Command to run supervisor which will manage both Node.js and Apache
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 
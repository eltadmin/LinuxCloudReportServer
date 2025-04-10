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
    apache2 \
    php8-apache2 \
    curl \
    supervisor

WORKDIR /app

# Install Node.js dependencies
COPY package*.json ./
RUN npm install --omit=dev

# Copy application files
COPY . .

# Configure Apache for dreport
RUN mkdir -p /var/www/html/dreport
RUN echo 'Listen 8015\n\
<VirtualHost *:8015>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    <Directory /var/www/html/dreport>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog /var/log/apache2/error.log\n\
    CustomLog /var/log/apache2/access.log combined\n\
</VirtualHost>' > /etc/apache2/conf.d/dreport.conf

# Create necessary directories
RUN mkdir -p logs updates /var/log/apache2

# Setup Apache modules and configurations
RUN sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf && \
    echo 'ServerName localhost' >> /etc/apache2/httpd.conf && \
    echo 'AddDefaultCharset UTF-8' >> /etc/apache2/httpd.conf

# Expose ports (HTTP, TCP, and Apache PHP)
EXPOSE 8080 8016 8015

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
command=/usr/sbin/httpd -D FOREGROUND\n\
autostart=true\n\
autorestart=true\n\
stderr_logfile=/var/log/supervisor/apache-stderr.log\n\
stdout_logfile=/var/log/supervisor/apache-stdout.log\n\
\n\
[program:setup-permissions]\n\
command=sh -c "chown -R apache:apache /var/www/html/dreport && chmod -R 755 /var/www/html/dreport"\n\
autostart=true\n\
autorestart=false\n\
startsecs=0\n\
startretries=1\n\
stderr_logfile=/var/log/supervisor/setup-stderr.log\n\
stdout_logfile=/var/log/supervisor/setup-stdout.log' > /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chown -R apache:apache /var/www/html

# Command to run supervisor which will manage both Node.js and Apache
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 
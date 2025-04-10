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

# Copy dreport to Apache's web directory
RUN mkdir -p /var/www/html/dreport
COPY ../dreport/ /var/www/html/dreport/

# Configure Apache for dreport
RUN echo '<VirtualHost *:8015>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog /var/log/apache2/error.log\n\
    CustomLog /var/log/apache2/access.log combined\n\
</VirtualHost>' > /etc/apache2/conf.d/dreport.conf

# Create necessary directories
RUN mkdir -p logs updates

# Expose ports (HTTP, TCP, and Apache PHP)
EXPOSE 8080 2909 8015

# Set up supervisor to manage multiple processes
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
command=httpd -D FOREGROUND\n\
autostart=true\n\
autorestart=true\n\
stderr_logfile=/var/log/supervisor/apache-stderr.log\n\
stdout_logfile=/var/log/supervisor/apache-stdout.log' > /etc/supervisor/conf.d/supervisord.conf

RUN mkdir -p /var/log/supervisor

# Set permissions
RUN chown -R apache:apache /var/www/html/dreport
RUN chmod -R 755 /var/www/html/dreport

# Command to run supervisor which will manage both Node.js and Apache
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 
FROM php:8.1-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    nodejs \
    npm \
    git \
    curl \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure nginx
COPY nginx.conf /etc/nginx/sites-enabled/default

# Configure supervisord
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Create necessary directories
RUN mkdir -p /var/log/supervisor

# Install node dependencies
RUN npm install

# Expose ports
EXPOSE 80 8016 8080

# Start supervisord
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 
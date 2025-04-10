FROM node:18-alpine

WORKDIR /app

# Install dependencies
RUN apk add --no-cache curl supervisor net-tools

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

# Set up supervisor to manage processes
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
stdout_logfile=/var/log/supervisor/node-stdout.log' > /etc/supervisor/conf.d/supervisord.conf

# Expose ports (HTTP and TCP)
EXPOSE 8080 8016

# Command to run supervisor which will manage Node.js
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 
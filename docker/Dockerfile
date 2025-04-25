FROM python:3.9-slim

WORKDIR /app

# Install dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy crypto module checker script
COPY docker/check_crypto.py /app/check_crypto.py
RUN chmod +x /app/check_crypto.py && \
    python /app/check_crypto.py

# Copy configuration setup script
COPY docker/setup_config.py /app/setup_config.py
RUN chmod +x /app/setup_config.py

# Copy application code
COPY src/ /app/
COPY config/ /app/config/
COPY docker/entrypoint.sh /app/entrypoint.sh
COPY debug_server.py /app/debug_server.py

# Create directories with proper permissions
RUN mkdir -p /app/logs /app/updates && \
    chmod 777 /app/logs /app/updates && \
    chmod +x /app/entrypoint.sh

# Expose ports
EXPOSE 8016
EXPOSE 8080

# Set environment variables
ENV PYTHONUNBUFFERED=1

# Run the application with entrypoint script
ENTRYPOINT ["/app/entrypoint.sh"] 
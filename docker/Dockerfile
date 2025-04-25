FROM python:3.9-slim

WORKDIR /app

# Install dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Install system dependencies for hardware detection
RUN apt-get update && apt-get install -y --no-install-recommends \
    util-linux \
    udev \
    && rm -rf /var/lib/apt/lists/*

# Copy application code
COPY src/ /app/src/
COPY config/ /app/config/

# Create directories with proper permissions
RUN mkdir -p /app/logs /app/updates && \
    chmod 777 /app/logs /app/updates

# Copy entrypoint script
COPY docker/entrypoint.sh /app/entrypoint.sh
RUN chmod +x /app/entrypoint.sh

# Expose ports
EXPOSE 8016
EXPOSE 8080

# Set environment variables
ENV PYTHONUNBUFFERED=1

# Set entrypoint script
ENTRYPOINT ["/app/entrypoint.sh"] 
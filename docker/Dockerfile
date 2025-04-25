FROM python:3.9-slim

WORKDIR /app

# Install dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY src/ /app/
COPY config/ /app/config/

# Create directories with proper permissions
RUN mkdir -p /app/logs /app/updates && \
    chmod 777 /app/logs /app/updates

# Expose ports
EXPOSE 8016
EXPOSE 8080

# Set environment variables
ENV PYTHONUNBUFFERED=1

# Run the application
CMD ["python", "server.py"] 
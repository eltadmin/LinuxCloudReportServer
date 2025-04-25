FROM python:3.9-slim

WORKDIR /app

# Install dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Test crypto module import
RUN python -c "try: import Crypto; print('Crypto module imported successfully'); \
    except ImportError: \
        try: import Cryptodome; print('Cryptodome module imported successfully'); \
        except ImportError: print('Failed to import crypto modules'); exit(1)"

# Copy application code
COPY src/ /app/
COPY config/ /app/config/
COPY docker/entrypoint.sh /app/entrypoint.sh

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
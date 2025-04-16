FROM python:3.9-slim

WORKDIR /app

# Install dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY . .

# Create directories
RUN mkdir -p logs

# Expose port
EXPOSE 8080

# Command to run the server
CMD ["python", "main.py", "--host", "0.0.0.0", "--port", "8080"] 
FROM node:18-alpine

WORKDIR /app

# Install dependencies first (for better caching)
COPY package*.json ./
RUN npm install --omit=dev

# Copy application files
COPY . .

# Create necessary directories
RUN mkdir -p logs updates

# Expose ports (HTTP and TCP)
EXPOSE 8080 2909

# Set non-root user for security
RUN addgroup -S appgroup && adduser -S appuser -G appgroup
RUN chown -R appuser:appgroup /app
USER appuser

# Command to run the application
CMD ["node", "server.js"] 
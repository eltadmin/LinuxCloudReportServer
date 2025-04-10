FROM node:18-alpine

WORKDIR /app

# Copy package.json and package-lock.json
COPY src/package.json ./

# Install dependencies
RUN npm install --production

# Copy application files
COPY src/ ./

# Create necessary directories
RUN mkdir -p logs Updates

# Copy configuration
COPY src/eboCloudReportServer.ini ./

# Expose ports
EXPOSE 8080
EXPOSE 8016

# Set environment variables
ENV NODE_ENV=production

# Run the server
CMD ["node", "server.js"] 
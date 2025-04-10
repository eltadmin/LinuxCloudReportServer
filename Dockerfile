FROM node:18-alpine

WORKDIR /app

# Install curl for healthcheck and other build dependencies
RUN apk add --no-cache curl python3 make g++

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
EXPOSE 8080 8016

# Set environment variables
ENV NODE_ENV=production
ENV DB_HOST=db
ENV DB_USER=dreports
ENV DB_PASSWORD=ftUk58_HoRs3sAzz8jk
ENV DB_NAME=dreports

# Add healthcheck endpoint
RUN echo '// Health check endpoint for Docker\napp.get("/health", (req, res) => res.status(200).send("OK"));' >> server.js

# Run the server
CMD ["node", "server.js"] 
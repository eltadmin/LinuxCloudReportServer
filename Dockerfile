FROM golang:1.20-alpine AS builder

WORKDIR /app

# Install necessary packages
RUN apk add --no-cache git gcc musl-dev

# Copy source code
COPY . .

# Build directly without trying to resolve external dependencies
RUN go build -mod=mod -o reportcom-server ./go_tcp_server.go

# Create a minimal production image
FROM alpine:3.17

# Set working directory
WORKDIR /app

# Install CA certificates and timezone data
RUN apk --no-cache add ca-certificates tzdata

# Create required directories
RUN mkdir -p /app/config /app/logs /app/updates /app/keys

# Copy the binary from builder stage
COPY --from=builder /app/reportcom-server /app/

# Copy config.ini to config directory
COPY config.ini /app/config/

# Set permissions
RUN chmod +x /app/reportcom-server

# Expose TCP and HTTP ports
EXPOSE 8016 8080

# Set environment variables for ports
ENV TCP_PORT=8016
ENV HTTP_PORT=8080
ENV TCP_HOST=0.0.0.0

# Run the server
CMD ["/app/reportcom-server", "-config", "/app/config/config.ini"] 
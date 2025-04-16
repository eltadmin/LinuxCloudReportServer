FROM golang:1.21-alpine AS builder

WORKDIR /app

# Copy the Go source file
COPY go_tcp_server.go .

# Build the Go application
RUN go build -o tcp_server go_tcp_server.go

# Use a minimal Alpine image for the final container
FROM alpine:latest

WORKDIR /app

# Create directory for key storage
RUN mkdir -p /app && chmod 755 /app

# Copy the binary from the builder stage
COPY --from=builder /app/tcp_server .

# Expose the TCP port
EXPOSE 8016

# Run the TCP server
CMD ["./tcp_server"] 
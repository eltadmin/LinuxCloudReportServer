FROM golang:1.18-alpine AS builder

# Set the working directory
WORKDIR /app

# Install necessary dependencies
RUN apk add --no-cache git gcc musl-dev

# Copy go.mod and go.sum first to leverage Docker caching
COPY go.mod go.sum ./
RUN go mod download

# Copy the rest of the source code
COPY . .

# Build the application
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o reportcom-server ./cmd/server

# Use a small alpine image for the runtime
FROM alpine:3.16

# Install necessary runtime dependencies
RUN apk add --no-cache ca-certificates tzdata

# Set the working directory
WORKDIR /app

# Copy the binary from the builder stage
COPY --from=builder /app/reportcom-server /app/

# Create necessary directories
RUN mkdir -p /app/logs /app/config /app/updates

# Copy config file
COPY config.ini /app/config/

# Set environment variables
ENV LOG_PATH=/app/logs

# Expose the ports
EXPOSE 9001 9002

# Set the entry point
ENTRYPOINT ["/app/reportcom-server"]
CMD ["--config", "/app/config/config.ini", "--log", "/app/logs"] 
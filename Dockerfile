FROM golang:1.20-alpine AS builder

# Install necessary build tools
RUN apk add --no-cache git gcc musl-dev 

# Set working directory
WORKDIR /build

# Copy only the go.mod and go.sum files first to leverage Docker layer caching
COPY go.mod go.sum* ./
RUN go mod download || true

# Copy the source code
COPY . .

# Build the application
RUN go build -mod=mod -o reportcom-server ./go_tcp_server.go

# Create the production image
FROM alpine:3.17

# Install runtime dependencies
RUN apk add --no-cache ca-certificates tzdata

# Set up directories
RUN mkdir -p /app/logs /app/updates /app/config /app/keys

# Copy the binary from the builder stage
COPY --from=builder /build/reportcom-server /app/

# Copy config files
COPY config.ini /app/config/

# Create a non-root user to run the application
RUN addgroup -S reportcom && adduser -S reportcom -G reportcom
RUN chown -R reportcom:reportcom /app

# Set working directory
WORKDIR /app

# Use the non-root user
USER reportcom

# Expose TCP and HTTP ports
EXPOSE 8016/tcp
EXPOSE 8080/tcp

# Define default environment variables
ENV TCP_PORT=8016 \
    HTTP_PORT=8080 \
    TCP_HOST=0.0.0.0

# Run the application
CMD ["/app/reportcom-server", "-config", "/app/config/config.ini"] 
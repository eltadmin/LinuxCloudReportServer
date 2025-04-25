FROM golang:1.20-alpine AS builder

WORKDIR /app

# Install necessary packages including git
RUN apk add --no-cache git

# Copy source code
COPY . .

# Initialize Go module if go.mod doesn't exist
RUN if [ ! -f go.mod ]; then \
    go mod init github.com/eltrade/reportcom-server && \
    go mod tidy \
    ; fi

# Download dependencies
RUN go mod download

# Build the application
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o reportcom-server .

# Create a minimal production image
FROM alpine:3.17

# Set working directory
WORKDIR /app

# Install CA certificates and timezone data
RUN apk --no-cache add ca-certificates tzdata

# Create required directories
RUN mkdir -p /app/config /app/logs /app/updates

# Copy the binary from builder stage
COPY --from=builder /app/reportcom-server /app/

# Copy config.ini if it exists
COPY config.ini* /app/config/

# Set permissions
RUN chmod +x /app/reportcom-server

# Expose TCP and HTTP ports
EXPOSE 8016 8015

# Run the server
CMD ["/app/reportcom-server", "-config", "/app/config/config.ini"] 
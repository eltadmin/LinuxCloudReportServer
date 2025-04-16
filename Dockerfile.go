FROM golang:1.21-alpine

WORKDIR /app

# Install required packages
RUN apk add --no-cache gcc musl-dev

# Copy go.mod and go.sum
COPY LinuxCloudReportServer/go.mod .
COPY LinuxCloudReportServer/go.sum* .

# Download dependencies
RUN go mod download

# Copy source code
COPY LinuxCloudReportServer/go_tcp_server.go .

# Build the Go application
RUN go build -o go_tcp_server go_tcp_server.go

# Expose port
EXPOSE 7777

# Run the application
CMD ["./go_tcp_server"] 
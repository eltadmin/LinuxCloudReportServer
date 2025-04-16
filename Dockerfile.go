FROM golang:1.21-alpine

WORKDIR /app

# Install required packages
RUN apk add --no-cache gcc musl-dev

# Copy go.mod and go.sum
COPY go.mod .
COPY go.sum* .

# Download dependencies
RUN go mod download
RUN go get github.com/mattn/go-sqlite3

# Copy source code
COPY go_tcp_server.go .

# Build the Go application
RUN go build -o go_tcp_server go_tcp_server.go

# Expose port
EXPOSE 7777

# Run the application
CMD ["./go_tcp_server"] 
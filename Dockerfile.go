FROM golang:1.21-alpine as builder

# Install build dependencies for SQLite
RUN apk add --no-cache gcc musl-dev

WORKDIR /app
COPY go_tcp_server.go .
COPY go.mod go.sum ./

# Download dependencies and build the app
RUN go mod download
RUN go build -o tcp_server go_tcp_server.go

FROM alpine:latest
WORKDIR /app
COPY --from=builder /app/tcp_server .
COPY dictionary.db ./

EXPOSE 8016
CMD ["./tcp_server"] 
@echo off
echo Building Go TCP server...
go build -o tcp_server.exe go_tcp_server.go

if %ERRORLEVEL% neq 0 (
    echo Failed to build Go TCP server
    exit /b 1
)

echo Starting Go TCP server...
tcp_server.exe 
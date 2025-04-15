@echo off
REM Script to run the TCP Proxy for traffic analysis

echo Starting TCP Proxy for traffic analysis
echo ======================================

REM Save current directory
cd /d %~dp0

REM Default values
set TARGET_HOST=127.0.0.1
set TARGET_PORT=8016
set LISTEN_PORT=8017

REM Check if parameters are provided
if not "%~1"=="" set TARGET_HOST=%~1
if not "%~2"=="" set TARGET_PORT=%~2

echo Stopping existing proxy container...
docker-compose stop proxy_server

echo Starting proxy_server...
set TARGET_HOST=%TARGET_HOST%
set TARGET_PORT=%TARGET_PORT%
docker-compose up -d proxy_server

echo.
echo TCP Proxy is running and listening on port %LISTEN_PORT%
echo Forwarding traffic to %TARGET_HOST%:%TARGET_PORT%
echo.
echo Instructions:
echo 1. Configure your Delphi client to connect to port %LISTEN_PORT% instead of %TARGET_PORT%
echo 2. The proxy will record all communication between client and server
echo 3. Check proxy_log.txt for detailed logs
echo.
echo Viewing logs (press Ctrl+C to stop):
docker-compose logs -f proxy_server 
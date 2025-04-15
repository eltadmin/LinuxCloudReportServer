@echo off
REM Script to test different INIT response formats

echo Testing different INIT response formats
echo ======================================

REM Save current directory
cd /d %~dp0

echo.
echo Available formats:
echo 1: Standard Delphi format with CRLF - LEN=8\r\nKEY=ABCDEFGH
echo 2: TIdReply direct format - 200 LEN=8 KEY=ABCDEFGH\r\n
echo 3: With numeric code and text - 200 OK\r\nLEN={len}\r\nKEY={key}\r\n
echo 4: Just key-value pairs with CRLF ending - LEN={len}\r\nKEY={key}\r\n
echo 5: Just key-value pairs with single LF - LEN={len}\nKEY={key}\n
echo 6: Just the key - ABCDEFGH
echo.

set /p FORMAT=Enter format to test (1-6): 

echo.
echo Testing format %FORMAT%
echo -----------------------

REM Set environment variable
set INIT_RESPONSE_FORMAT=%FORMAT%

echo Stopping services...
docker-compose down

echo Starting services with format %FORMAT%...
docker-compose up -d

REM Wait for server to start
echo Waiting for server to start...
timeout /t 5 /nobreak

echo Server logs:
docker-compose logs report_server | findstr "Using INIT response format"

echo.
echo Press any key to exit
pause 
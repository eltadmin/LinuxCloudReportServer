@echo off
rem Script to run a local test of the Linux Cloud Report Server on Windows

echo Linux Cloud Report Server - Local Test Setup
echo ----------------------------------------

rem Check for Python 3.9+
python --version > temp.txt
set /p python_version=<temp.txt
del temp.txt
echo %python_version%

rem Create virtual environment if it doesn't exist
if not exist "venv" (
    echo Creating virtual environment...
    python -m venv venv
    echo ✓ Virtual environment created
) else (
    echo ✓ Virtual environment exists
)

rem Activate virtual environment
echo Activating virtual environment...
call venv\Scripts\activate.bat
echo ✓ Virtual environment activated

rem Install dependencies
echo Installing dependencies...
pip install -e .
echo ✓ Dependencies installed

rem Create necessary directories
echo Creating necessary directories...
if not exist "logs" mkdir logs
if not exist "Updates" mkdir Updates
echo ✓ Directories created

rem Check for configuration file
if not exist "config\eboCloudReportServer.ini" (
    echo ⚠ Configuration file not found at config\eboCloudReportServer.ini
    echo Creating a sample configuration file...
    
    if not exist "config" mkdir config
    
    echo [COMMONSETTINGS] > config\eboCloudReportServer.ini
    echo CommInterfaceCount=1 >> config\eboCloudReportServer.ini
    echo. >> config\eboCloudReportServer.ini
    echo [REGISTRATION INFO] >> config\eboCloudReportServer.ini
    echo SERIAL NUMBER=987654321 >> config\eboCloudReportServer.ini
    echo KEY=TestKey12345 >> config\eboCloudReportServer.ini
    echo. >> config\eboCloudReportServer.ini
    echo [SRV_1_COMMON] >> config\eboCloudReportServer.ini
    echo TraceLogEnabled=1 >> config\eboCloudReportServer.ini
    echo UpdateFolder=Updates >> config\eboCloudReportServer.ini
    echo. >> config\eboCloudReportServer.ini
    echo [SRV_1_HTTP] >> config\eboCloudReportServer.ini
    echo HTTP_IPInterface=0.0.0.0 >> config\eboCloudReportServer.ini
    echo HTTP_Port=8080 >> config\eboCloudReportServer.ini
    echo. >> config\eboCloudReportServer.ini
    echo [SRV_1_TCP] >> config\eboCloudReportServer.ini
    echo TCP_IPInterface=0.0.0.0 >> config\eboCloudReportServer.ini
    echo TCP_Port=8016 >> config\eboCloudReportServer.ini
    echo. >> config\eboCloudReportServer.ini
    echo [SRV_1_AUTHSERVER] >> config\eboCloudReportServer.ini
    echo REST_URL=http://localhost:8015/dreport/api.php >> config\eboCloudReportServer.ini
    echo. >> config\eboCloudReportServer.ini
    echo [SRV_1_HTTPLOGINS] >> config\eboCloudReportServer.ini
    echo user=pass$123 >> config\eboCloudReportServer.ini
    echo admin=admin123 >> config\eboCloudReportServer.ini
    
    echo ✓ Sample configuration file created
) else (
    echo ✓ Configuration file exists
)

rem Set up environment variables
set DB_HOST=localhost
set DB_USER=dreports
set DB_PASSWORD=dreports
set DB_NAME=dreports
set AUTH_SERVER_URL=http://localhost:8015/dreport/api.php
set PYTHONPATH=%CD%

echo.
echo ----------------------------------------
echo Environment is set up. You can run the server with:
echo python main.py
echo.
echo Or test the TCP client connection with:
echo python test_client_connection.py --host localhost --port 8016 -v
echo.
echo Note: This test setup doesn't include a MySQL database.
echo For full functionality, you need to start the Docker containers with docker-compose.
echo ----------------------------------------

rem Ask if user wants to run the server
set /p run_server=Do you want to run the server now? (y/n) 

if "%run_server%"=="y" (
    echo Starting the server...
    echo Press Ctrl+C to stop the server
    python main.py
) else if "%run_server%"=="Y" (
    echo Starting the server...
    echo Press Ctrl+C to stop the server
    python main.py
) else (
    echo You can run the server later with 'python main.py'
)

pause 
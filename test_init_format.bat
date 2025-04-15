@echo off
REM Windows batch script to run the INIT format test

echo Testing INIT command format with server at localhost:8016
python test_init_format.py

echo.
echo Press any key to exit...
pause > nul 
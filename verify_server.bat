@echo off
REM Script to verify the server is running correctly

echo Verifying server status...

REM Run the verification script
python verify_server.py %1 %2

if %ERRORLEVEL% EQU 0 (
    echo Server verification completed successfully!
) else (
    echo Server verification failed!
)

pause 
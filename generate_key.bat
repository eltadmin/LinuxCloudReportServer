@echo off
setlocal enabledelayedexpansion

echo ServerKeyGen Tool for ReportCom

:: Проверяваме дали е подаден сериен номер
if "%1"=="" (
  :: Генерираме машинно-специфичен ID
  for /f "tokens=2 delims==" %%I in ('wmic diskdrive get SerialNumber /value ^| findstr "SerialNumber"') do set SERIAL_NUMBER=%%I
  echo Използване на автоматично генериран сериен номер: %SERIAL_NUMBER%
) else (
  set SERIAL_NUMBER=%1
  echo Използване на подаден сериен номер: %SERIAL_NUMBER%
)

:: Компилираме и изпълняваме keygen.go
go build -o keygen.exe keygen.go
keygen.exe %SERIAL_NUMBER% > keygen_output.txt

:: Създаваме директория за config ако не съществува
if not exist config mkdir config

:: Извличаме ключа от изхода
set KEY=
for /f "tokens=1,* delims=:" %%A in ('findstr "Generated Key:" keygen_output.txt') do set KEY=%%B
set KEY=%KEY: =%

:: Функция за обновяване на INI файл
call :update_ini "config.ini" "REGISTRATION INFO" "SERIAL NUMBER" "%SERIAL_NUMBER%"
call :update_ini "config.ini" "REGISTRATION INFO" "KEY" "%KEY%"

echo Конфигурационният файл config.ini е обновен с новия ключ!
echo За да използвате генерирания ключ в Docker, изпълнете:
echo docker-compose build --no-cache
echo docker-compose up -d reportcom-server

del keygen_output.txt
goto :EOF

:update_ini
set FILE=%~1
set SECTION=%~2
set KEY=%~3
set VALUE=%~4

:: Ако файлът не съществува, създаваме го
if not exist %FILE% (
  echo [%SECTION%]> %FILE%
  echo %KEY%=%VALUE%>> %FILE%
  goto :EOF
)

:: Проверяваме дали секцията съществува
findstr /b /c:"[%SECTION%]" %FILE% >nul
if errorlevel 1 (
  :: Секцията не съществува, добавяме я
  echo.>> %FILE%
  echo [%SECTION%]>> %FILE%
  echo %KEY%=%VALUE%>> %FILE%
) else (
  :: Секцията съществува, обновяваме стойността
  set "TEMPFILE=%TEMP%\%RANDOM%%RANDOM%.tmp"
  set "FOUND_SECTION="
  set "UPDATED="
  
  for /F "tokens=* usebackq" %%A in ("%FILE%") do (
    if "%%A"=="[%SECTION%]" (
      echo %%A>> "%TEMPFILE%"
      set "FOUND_SECTION=1"
    ) else if "!FOUND_SECTION!"=="1" (
      if "%%A"=="" (
        echo %%A>> "%TEMPFILE%"
      ) else if "%%A:~0,1%"=="[" (
        if "!UPDATED!"=="" (
          echo %KEY%=%VALUE%>> "%TEMPFILE%"
          set "UPDATED=1"
        )
        echo %%A>> "%TEMPFILE%"
        set "FOUND_SECTION="
      ) else if /I "%%A:~0,%KEY_LEN%"=="%KEY%=" (
        echo %KEY%=%VALUE%>> "%TEMPFILE%"
        set "UPDATED=1"
      ) else (
        echo %%A>> "%TEMPFILE%"
      )
    ) else (
      echo %%A>> "%TEMPFILE%"
    )
  )
  
  :: Ако не е обновено в рамките на секцията, добавяме го
  if "!FOUND_SECTION!"=="1" if "!UPDATED!"=="" (
    echo %KEY%=%VALUE%>> "%TEMPFILE%"
  )
  
  copy /Y "%TEMPFILE%" "%FILE%" >nul
  del "%TEMPFILE%"
)

goto :EOF 
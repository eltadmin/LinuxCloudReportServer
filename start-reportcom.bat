@echo off
setlocal

echo ReportCom Server - Контролен скрипт
echo.

if "%1"=="" goto help
if "%1"=="help" goto help
if "%1"=="build" goto build
if "%1"=="up" goto up
if "%1"=="down" goto down
if "%1"=="stop" goto down
if "%1"=="logs" goto logs
if "%1"=="rebuild" goto rebuild
if "%1"=="status" goto status
goto help

:build
echo Изграждане на Docker образ...
docker-compose build reportcom-server
goto end

:up
echo Стартиране на контейнера...
docker-compose up -d reportcom-server
echo Контейнерът е стартиран. Използвайте '%0 logs' за да видите логовете.
goto end

:down
echo Спиране на контейнера...
docker-compose down reportcom-server
goto end

:logs
echo Показване на логове...
docker-compose logs -f reportcom-server
goto end

:rebuild
echo Повторно изграждане без кеш...
docker-compose build --no-cache reportcom-server
goto end

:status
echo Проверка на статуса...
docker-compose ps reportcom-server
goto end

:help
echo Употреба: %0 [команда]
echo.
echo Команди:
echo   build       Изграждане на Docker образ
echo   up          Стартиране на контейнера
echo   down        Спиране на контейнера
echo   logs        Показване на логове
echo   rebuild     Повторно изграждане без кеш
echo   stop        Спиране на контейнера
echo   status      Проверка на статуса
echo   help        Тази помощна информация
echo.

:end
endlocal 
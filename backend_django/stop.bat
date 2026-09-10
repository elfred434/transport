@echo off
REM ============================================================
REM  backend_django\stop.bat
REM  Arrete le serveur Django (runserver) et libere le port 8000.
REM  Usage: double-clic ou  stop.bat
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0"

echo [i] Arret du backend Django...

REM Tue tout processus python qui execute manage.py runserver
set "FOUND=0"
for /f "tokens=2" %%P in ('tasklist /fi "imagename eq python.exe" /fo list ^| findstr /i "PID:" 2^>nul') do (
    wmic process where "ProcessId=%%P and CommandLine like '%%manage.py runserver%%'" get ProcessId 2>nul | findstr /r "[0-9]" >nul && (
        taskkill /F /PID %%P >nul 2>&1
        echo [OK] Processus %%P arrete
        set "FOUND=1"
    )
)

REM Libere aussi le port 8000 au cas ou
for /f "tokens=5" %%P in ('netstat -aon ^| findstr ":8000 " ^| findstr LISTENING') do (
    taskkill /F /PID %%P >nul 2>&1
    echo [OK] Port 8000 libere (PID %%P)
    set "FOUND=1"
)

if "%FOUND%"=="0" echo [i] Aucun serveur backend en cours.
echo.
timeout /t 2 >nul

@echo off
REM ============================================================
REM  backend_django\stop.bat — Arrete le backend, libere port 8000
REM ============================================================
chcp 65001 >nul
cd /d "%~dp0"
title [Transport.bj] Stop Backend

echo [i] Arret du backend Django...
echo.

set "FOUND=0"

REM Tue tout python.exe qui execute "manage.py runserver"
for /f "tokens=2 delims= " %%P in ('tasklist /fi "imagename eq python.exe" /fo list ^| findstr /i "PID:" 2^>nul') do (
    wmic process where "ProcessId=%%P and CommandLine like '%%manage.py runserver%%'" get ProcessId 2>nul | findstr /r "[0-9]" >nul
    if not errorlevel 1 (
        taskkill /F /PID %%P >nul 2>&1
        echo [OK] Processus Python %%P arrete (manage.py runserver)
        set "FOUND=1"
    )
)

REM Libere le port 8000 au cas ou
for /f "tokens=5" %%P in ('netstat -ano ^| findstr /r /c:":8000 .*LISTENING"') do (
    taskkill /F /PID %%P >nul 2>&1
    if not errorlevel 1 (
        echo [OK] Port 8000 libere (PID %%P)
        set "FOUND=1"
    )
)

if "%FOUND%"=="0" echo [i] Aucun serveur backend en cours d'execution.

echo.
echo [OK] Fait.
timeout /t 3 >nul

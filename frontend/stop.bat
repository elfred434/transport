@echo off
REM ============================================================
REM  frontend\stop.bat
REM  Arrete le serveur Vite et libere le port 5173.
REM  Usage: double-clic ou  stop.bat
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0"

echo [i] Arret du frontend Vite...

set "FOUND=0"
REM Tue tout node.exe qui execute vite
for /f "tokens=2" %%P in ('tasklist /fi "imagename eq node.exe" /fo list ^| findstr /i "PID:" 2^>nul') do (
    wmic process where "ProcessId=%%P and CommandLine like '%%vite%%'" get ProcessId 2>nul | findstr /r "[0-9]" >nul && (
        taskkill /F /PID %%P >nul 2>&1
        echo [OK] Processus node/vite %%P arrete
        set "FOUND=1"
    )
)

REM Libere le port 5173
for /f "tokens=5" %%P in ('netstat -aon ^| findstr ":5173 " ^| findstr LISTENING') do (
    taskkill /F /PID %%P >nul 2>&1
    echo [OK] Port 5173 libere (PID %%P)
    set "FOUND=1"
)

if "%FOUND%"=="0" echo [i] Aucun serveur frontend en cours.
echo.
timeout /t 2 >nul

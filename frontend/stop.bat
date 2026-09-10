@echo off
REM ============================================================
REM  frontend\stop.bat — Arrete Vite, libere port 5173
REM ============================================================
chcp 65001 >nul
cd /d "%~dp0"
title [Transport.bj] Stop Frontend

echo [i] Arret du frontend Vite...
echo.

set "FOUND=0"

REM Tue node.exe qui execute "vite"
for /f "tokens=2 delims= " %%P in ('tasklist /fi "imagename eq node.exe" /fo list ^| findstr /i "PID:" 2^>nul') do (
    wmic process where "ProcessId=%%P and CommandLine like '%%vite%%'" get ProcessId 2>nul | findstr /r "[0-9]" >nul
    if not errorlevel 1 (
        taskkill /F /PID %%P >nul 2>&1
        echo [OK] Processus Node %%P arrete (Vite)
        set "FOUND=1"
    )
)

REM Libere port 5173
for /f "tokens=5" %%P in ('netstat -ano ^| findstr /r /c:":5173 .*LISTENING"') do (
    taskkill /F /PID %%P >nul 2>&1
    if not errorlevel 1 (
        echo [OK] Port 5173 libere (PID %%P)
        set "FOUND=1"
    )
)

if "%FOUND%"=="0" echo [i] Aucun serveur frontend en cours d'execution.

echo.
echo [OK] Fait.
timeout /t 3 >nul

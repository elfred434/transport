@echo off
REM ============================================================
REM  start.bat — Demarre BACKEND + FRONTEND dans 2 fenetres
REM ============================================================
chcp 65001 >nul
cd /d "%~dp0"
title [Transport.bj] DEMARRAGE

echo ============================================================
echo   TRANSPORT.BJ — DEMARRAGE
echo ============================================================
echo.
echo Ouverture de 2 fenetres :
echo   - Backend Django  (port 8000)
echo   - Frontend Vite   (port 5173)
echo.

REM Lance le backend dans une NOUVELLE fenetre
start "[Transport.bj] BACKEND Django :8000" cmd /k "cd /d %cd%\backend_django && start.bat"
timeout /t 3 /nobreak >nul

REM Lance le frontend dans une autre NOUVELLE fenetre
start "[Transport.bj] FRONTEND Vite :5173" cmd /k "cd /d %cd%\frontend && start.bat"
timeout /t 2 /nobreak >nul

echo.
echo ============================================================
echo   SERVEURS LANCES !
echo.
echo     API     :  http://localhost:8000/api
echo     Web     :  http://localhost:5173
echo     Admin   :  admin@transport.bj / Admin@12345
echo.
echo   Pour arreter : double-cliquez sur stop.bat
echo   (ou fermez les 2 fenetres)
echo ============================================================
echo.
timeout /t 8 >nul

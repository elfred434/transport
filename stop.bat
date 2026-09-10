@echo off
REM ============================================================
REM  stop.bat — Arrete BACKEND + FRONTEND
REM ============================================================
chcp 65001 >nul
cd /d "%~dp0"
title [Transport.bj] ARRET

echo ============================================================
echo   ARRET DES SERVEURS
echo ============================================================
echo.

echo [i] Arret backend...
call backend_django\stop.bat
echo.

echo [i] Arret frontend...
call frontend\stop.bat
echo.

echo ============================================================
echo   TOUS LES SERVEURS SONT ARRETES.
echo ============================================================
echo.
timeout /t 3 >nul

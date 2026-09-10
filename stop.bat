@echo off
REM ============================================================
REM  stop.bat — Arrete backend + frontend et libere les ports 8000/5173
REM  Usage: double-clic ou  stop.bat
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo [i] Arret des serveurs...
echo.

call backend_django\stop.bat
call frontend\stop.bat

echo.
echo [OK] Tous les serveurs sont arretes.
echo.
timeout /t 2 >nul

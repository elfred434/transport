@echo off
REM ============================================================
REM  frontend\start.bat
REM  Demarre le serveur Vite dev sur le port 5173.
REM  Usage: double-clic ou  start.bat
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0"

if not exist node_modules (
    echo [!] node_modules absent. Lancement de setup.bat...
    call setup.bat
    if errorlevel 1 exit /b 1
)

echo.
echo ============================================
echo   Frontend Vite en ligne :
echo     URL:  http://localhost:5173
echo ============================================
echo.
echo (Laisse cette fenetre ouverte. Arretez avec stop.bat ou Ctrl+C)
echo.

call npm run dev -- --host 0.0.0.0 --port 5173
pause

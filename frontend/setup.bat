@echo off
REM ============================================================
REM  frontend\setup.bat
REM  Installe node_modules via npm (Vite + React).
REM  Usage: double-clic ou  setup.bat
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo === Setup FRONTEND React/Vite ===
echo.

where node >nul 2>&1
if errorlevel 1 ( echo [X] Node.js introuvable — installez depuis https://nodejs.org & pause & exit /b 1 )
where npm  >nul 2>&1
if errorlevel 1 ( echo [X] npm introuvable & pause & exit /b 1 )

echo [i] node & node --version
echo [i] npm  & call npm --version
echo.

if exist node_modules (
    echo [OK] node_modules deja present
    echo [i] npm ci...
    call npm ci --prefer-offline 2>nul
    if errorlevel 1 (
        echo [!] npm ci a echoue — npm install...
        call npm install
    )
) else (
    echo [i] npm install...
    call npm install
)
if errorlevel 1 ( echo [X] Echec npm install & pause & exit /b 1 )

echo.
echo [OK] === Frontend pret — lancez start.bat pour demarrer ===
pause

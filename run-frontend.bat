@echo off
REM ============================================================
REM  run-frontend.bat — Démarre le frontend Vite (Windows)
REM  Ouvre un 2e terminal et double-clique sur ce fichier
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0frontend"

if not exist node_modules (
    echo [i] npm install...
    call npm install
)

echo.
echo [OK] Frontend Vite sur http://localhost:5173
echo (Laisse cette fenetre ouverte. Ctrl+C pour arreter.)
echo.
call npm run dev -- --host 0.0.0.0
pause

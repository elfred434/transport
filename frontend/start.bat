@echo off
REM ============================================================
REM  frontend\start.bat — Demarre Vite dev server :5173
REM ============================================================
chcp 65001 >nul
cd /d "%~dp0"
title [Transport.bj] Frontend Vite :5173

echo ============================================================
echo   FRONTEND VITE — DEMARRAGE
echo   Dossier: %cd%
echo ============================================================
echo.

if not exist node_modules (
    echo [!] node_modules absent. Lancement de setup.bat...
    call setup.bat
    if errorlevel 1 (
        echo [ERREUR] Setup a echoue.
        pause
        exit /b 1
    )
)

echo ============================================================
echo   FRONTEND EN LIGNE :
echo     URL:  http://localhost:5173
echo ============================================================
echo.
echo [i] Cette fenetre affiche les logs en direct.
echo [i] Pour arreter: fermez cette fenetre ou lancez stop.bat
echo.

call npm run dev -- --host 0.0.0.0 --port 5173

echo.
echo [i] Le serveur s'est arrete.
pause

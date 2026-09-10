@echo off
REM ============================================================
REM  setup.bat — Installation COMPLETE (backend + frontend)
REM ============================================================
chcp 65001 >nul
cd /d "%~dp0"
title [Transport.bj] INSTALLATION COMPLETE

echo.
echo ************************************************************
echo   TRANSPORT.BJ — INSTALLATION COMPLETE
echo   Dossier: %cd%
echo ************************************************************
echo.
echo Ce script va installer :
echo   - BACKEND  : environnement Python (venv) + Django + dependances
echo   - FRONTEND : node_modules + React/Vite
echo.
echo Cela peut prendre 2 a 5 minutes la premiere fois.
echo.
pause

echo.
echo ============================================================
echo   1/2 — BACKEND DJANGO
echo ============================================================
call backend_django\setup.bat
if errorlevel 1 (
    echo.
    echo [ERREUR] Setup backend a echoue.
    pause
    exit /b 1
)

echo.
echo ============================================================
echo   2/2 — FRONTEND REACT/VITE
echo ============================================================
call frontend\setup.bat
if errorlevel 1 (
    echo.
    echo [ERREUR] Setup frontend a echoue.
    pause
    exit /b 1
)

echo.
echo ************************************************************
echo   INSTALLATION TERMINEE AVEC SUCCES !
echo.
echo   Pour demarrer :  double-cliquez sur start.bat
echo ************************************************************
echo.
pause

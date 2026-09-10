@echo off
REM ============================================================
REM  start.bat — Demarre backend ET frontend dans 2 fenetres (Windows)
REM  Usage: double-clic ou  start.bat
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ============================================
echo   Transport.bj - Demarrage BACKEND + FRONTEND
echo ============================================
echo.

REM Backend dans cette fenetre
start "Transport.bj - Backend Django :8000" cmd /k "cd /d %cd%\backend_django && start.bat"
timeout /t 2 >nul

REM Frontend dans une autre fenetre
start "Transport.bj - Frontend Vite :5173" cmd /k "cd /d %cd%\frontend && start.bat"

echo [OK] Deux fenetres ont ete ouvertes (backend + frontend).
echo [OK] API   http://localhost:8000/api
echo [OK] Web   http://localhost:5173
echo.
echo Pour arreter: fermez les fenetres ou double-cliquez sur stop.bat
echo.
timeout /t 5 >nul

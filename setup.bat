@echo off
REM ============================================================
REM  setup.bat — Installation complete backend + frontend (Windows)
REM  Délègue vers backend_django\setup.bat et frontend\setup.bat
REM  Usage: double-clic ou  setup.bat
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ============================================
echo   Transport.bj - Installation complete
echo ============================================
echo.

echo [i] >>> Backend
call backend_django\setup.bat
if errorlevel 1 pause & exit /b 1
echo.

echo [i] >>> Frontend
call frontend\setup.bat
if errorlevel 1 pause & exit /b 1
echo.

echo ============================================
echo   Installation terminee !
echo   Pour demarrer : double-cliquez sur start.bat
echo ============================================
echo.
pause

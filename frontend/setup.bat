@echo off
REM ============================================================
REM  frontend\setup.bat — Installe node_modules (npm)
REM ============================================================
chcp 65001 >nul
cd /d "%~dp0"
title [Transport.bj] Setup Frontend

echo ============================================================
echo   FRONTEND REACT/VITE — SETUP
echo   Dossier: %cd%
echo ============================================================
echo.

REM Verifier Node
echo [1/3] Verification de Node.js...
where node >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] Node.js n'est pas installe.
    echo Telecharge Node.js LTS depuis https://nodejs.org
    echo (LTS = version recommandee, coche "Add to PATH").
    echo.
    pause
    exit /b 1
)
node --version
call npm --version
echo [OK] Node + npm trouves
echo.

REM Installer dependances
echo [2/3] Installation des dependances (npm)...
echo       (cela peut prendre 1-3 minutes la premiere fois)
if exist node_modules (
    echo [i] node_modules deja present — npm ci pour verrouiller les versions
    call npm ci --prefer-offline
    if errorlevel 1 (
        echo [!] npm ci a echoue, fallback npm install...
        call npm install
    )
) else (
    call npm install
)
if errorlevel 1 (
    echo [ERREUR] Echec de npm install. Verifie ta connexion internet.
    pause
    exit /b 1
)
echo [OK] Dependances frontend installees
echo.

REM Verification build
echo [3/3] Verification rapide...
if exist node_modules\vite\bin\vite.js (
    echo [OK] Vite est bien installe
) else (
    echo [ATTENTION] vite.js introuvable — npm install a peut-etre echoue silencieusement.
    echo Relance setup.bat.
    pause
    exit /b 1
)
echo.

echo ============================================================
echo   SETUP FRONTEND TERMINE !
echo   Lance start.bat (dans ce dossier) pour demarrer le serveur.
echo ============================================================
echo.
pause

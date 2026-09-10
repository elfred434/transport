@echo off
REM ============================================================
REM  setup.bat — Installation automatique backend + frontend (Windows)
REM  Usage: double-clic ou  setup.bat
REM ============================================================
setlocal EnableDelayedExpansion
chcp 65001 >nul
cd /d "%~dp0"
set ROOT=%cd%

echo.
echo ============================================
echo   Transport.bj - Installation automatique
echo ============================================
echo.

REM ---------- Prérequis ----------
where python >nul 2>&1
if errorlevel 1 (
    echo [X] Python introuvable. Installe Python 3.10+ depuis https://python.org (coche "Add to PATH")
    pause & exit /b 1
)
where node >nul 2>&1
if errorlevel 1 (
    echo [X] Node.js introuvable. Installe Node depuis https://nodejs.org
    pause & exit /b 1
)
where npm >nul 2>&1
if errorlevel 1 (
    echo [X] npm introuvable avec Node.
    pause & exit /b 1
)
echo [i] Python:
python --version
echo [i] Node:
node --version
echo.

REM ---------- 1. BACKEND ----------
echo [i] === Backend Django ===
cd /d "%ROOT%\backend_django"

if not exist venv (
    echo [i] Creation du venv backend_django\venv ...
    python -m venv venv
    echo [OK] venv cree
) else (
    echo [OK] venv deja present
)

call venv\Scripts\activate.bat
echo [OK] venv active :
where python

echo [i] Mise a jour pip...
python -m pip install --upgrade pip --quiet

echo [i] Installation dependances Python (requirements.txt)...
pip install -r requirements.txt
if errorlevel 1 ( echo [X] Echec pip install & pause & exit /b 1 )
echo [OK] Dependances Python installees

echo [i] Application des migrations...
python manage.py migrate
if errorlevel 1 ( echo [X] Echec migrate & pause & exit /b 1 )

echo [i] Creation du compte admin si absent...
python manage.py shell -c "from accounts.models import User; u=User.objects.filter(email='admin@transport.bj'); print('admin@transport.bj / Admin@12345  DEJA EXISTANT' if u.exists() else 'admin@transport.bj / Admin@12345  CREE' or User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin'))"
echo [OK] Backend pret
echo.

REM ---------- 2. FRONTEND ----------
echo [i] === Frontend React/Vite ===
cd /d "%ROOT%\frontend"

if not exist node_modules (
    echo [i] npm install...
    call npm install
    if errorlevel 1 ( echo [X] Echec npm install & pause & exit /b 1 )
    echo [OK] node_modules installe
) else (
    echo [OK] node_modules deja present
    echo [i] Verification npm ci...
    call npm ci --prefer-offline 2>nul || call npm install
)
echo [OK] Frontend pret
echo.

REM ---------- Résumé ----------
cd /d "%ROOT%"
echo ============================================
echo   Installation terminee !
echo ============================================
echo.
echo Pour DEMARRER :
echo.
echo   - Backend (API Django port 8000):   double-clic sur run.bat
echo   - Frontend (Vite dev):             cd frontend ^&^& npm run dev
echo.
echo Admin: admin@transport.bj / Admin@12345
echo.
pause

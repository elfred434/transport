@echo off
REM ============================================================
REM  backend_django\setup.bat
REM  Installe le venv Python + dependances du backend Django.
REM ============================================================
chcp 65001 >nul
cd /d "%~dp0"
title [Transport.bj] Setup Backend Django

echo ============================================================
echo   BACKEND DJANGO — SETUP
echo   Dossier: %cd%
echo ============================================================
echo.

REM Verifier Python
echo [1/5] Verification de Python...
where python >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] Python n'est pas dans le PATH.
    echo Installe Python 3.10+ depuis https://www.python.org
    echo et COCHE "Add Python to PATH" pendant l'installation.
    echo.
    pause
    exit /b 1
)
python --version
echo [OK] Python trouve
echo.

REM Creer venv si absent
echo [2/5] Verification / creation du venv...
if exist venv\Scripts\activate.bat (
    echo [OK] venv deja present
) else (
    echo [i] Creation du venv (venv\)...
    python -m venv venv
    if errorlevel 1 (
        echo [ERREUR] Impossible de creer le venv.
        pause
        exit /b 1
    )
    echo [OK] venv cree
)
echo.

REM Activer venv
echo [3/5] Activation du venv...
call venv\Scripts\activate.bat
if errorlevel 1 (
    echo [ERREUR] Impossible d'activer le venv.
    pause
    exit /b 1
)
echo [OK] venv active :
where python
echo.

REM Installer dependances
echo [4/5] Installation des dependances Python (requirements.txt)...
echo       (cela peut prendre 1-2 minutes la premiere fois)
python -m pip install --upgrade pip
pip install -r requirements.txt
if errorlevel 1 (
    echo [ERREUR] Echec de pip install.
    echo Verifie ta connexion internet et reessaye.
    pause
    exit /b 1
)
echo [OK] Dependances installees
echo.

REM Migrations + admin
echo [5/5] Migrations et compte admin...
python manage.py migrate
if errorlevel 1 (
    echo [ERREUR] Echec des migrations.
    pause
    exit /b 1
)
python manage.py shell -c "from accounts.models import User; User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin') if not User.objects.filter(email='admin@transport.bj').exists() else print('  -> admin@transport.bj deja present')"
echo.

echo ============================================================
echo   SETUP BACKEND TERMINE !
echo   Lance start.bat (dans ce dossier) pour demarrer le serveur.
echo ============================================================
echo.
pause

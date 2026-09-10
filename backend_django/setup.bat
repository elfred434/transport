@echo off
REM ============================================================
REM  backend_django\setup.bat
REM  Installe le venv Python + dependances du backend Django.
REM  Usage: double-clic ou  setup.bat
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo === Setup BACKEND Django ===
echo.

where python >nul 2>&1
if errorlevel 1 (
    echo [X] Python introuvable. Installe Python 3.10+ (coche "Add to PATH" a l'install).
    pause & exit /b 1
)
python --version

if not exist venv (
    echo [i] Creation du venv ^(.\venv^) ...
    python -m venv venv
    echo [OK] venv cree
) else (
    echo [OK] venv deja present
)

call venv\Scripts\activate.bat
echo [OK] venv active

echo [i] Mise a jour pip...
python -m pip install --upgrade pip --quiet

echo [i] Installation dependances (requirements.txt)...
pip install -r requirements.txt
if errorlevel 1 ( echo [X] Echec pip install & pause & exit /b 1 )
echo [OK] Dependances installees

echo [i] Migrations base...
python manage.py migrate
if errorlevel 1 ( echo [X] Echec migrate & pause & exit /b 1 )

echo [i] Compte admin (auto si absent)...
python manage.py shell -c "from accounts.models import User; u=User.objects.filter(email='admin@transport.bj'); User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin') if not u.exists() else print('admin deja present')"

echo.
echo [OK] === Backend pret — lancez start.bat pour demarrer ===
pause

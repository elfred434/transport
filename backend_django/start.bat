@echo off
REM ============================================================
REM  backend_django\start.bat
REM  Demarre le serveur Django (runserver) sur le port 8000.
REM  Usage: double-clic ou  start.bat
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0"

if not exist venv (
    echo [!] venv absent. Lancement automatique de setup.bat...
    call setup.bat
    if errorlevel 1 exit /b 1
)

call venv\Scripts\activate.bat

echo [i] Migrations...
python manage.py migrate

echo [i] Compte admin (auto si absent)...
python manage.py shell -c "from accounts.models import User; User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin') if not User.objects.filter(email='admin@transport.bj').exists() else print('admin OK')"

echo.
echo ============================================
echo   Backend Django en ligne :
echo     API:    http://localhost:8000/api
echo     Admin:  http://localhost:8000/admin/django/
echo     Login:  admin@transport.bj / Admin@12345
echo ============================================
echo.
echo (Laisse cette fenetre ouverte. Arretez avec stop.bat ou Ctrl+C)
echo.

python manage.py runserver 0.0.0.0:8000
pause

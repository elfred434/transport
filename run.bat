@echo off
REM ============================================================
REM  run.bat — Démarre le backend Django (Windows)
REM  Double-clique sur ce fichier pour lancer l'API.
REM  Pour le frontend: ouvre un 2e terminal et fais "cd frontend && npm run dev"
REM ============================================================
setlocal
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ============================================
echo   Transport.bj - Demarrage BACKEND
echo ============================================
echo.

cd /d "%~dp0backend_django"

if not exist venv (
    echo [!] venv absent. Lancement de setup.bat...
    call "%~dp0setup.bat"
    if errorlevel 1 pause & exit /b 1
)

call venv\Scripts\activate.bat

echo [i] Migrations...
python manage.py migrate

echo [i] Compte admin (auto si absent)...
python manage.py shell -c "from accounts.models import User; u=User.objects.filter(email='admin@transport.bj'); User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin') if not u.exists() else print('admin OK')"

echo.
echo [OK] Backend sur http://localhost:8000/api
echo [OK] Admin Django http://localhost:8000/admin/django/
echo [OK] Compte: admin@transport.bj / Admin@12345
echo.
echo (Laisse cette fenetre ouverte. Ctrl+C pour arreter.)
echo.

python manage.py runserver 0.0.0.0:8000
pause

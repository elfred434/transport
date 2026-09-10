@echo off
REM ============================================================
REM  backend_django\start.bat — Demarre le serveur Django :8000
REM ============================================================
chcp 65001 >nul
cd /d "%~dp0"
title [Transport.bj] Backend Django :8000

echo ============================================================
echo   BACKEND DJANGO — DEMARRAGE
echo   Dossier: %cd%
echo ============================================================
echo.

REM Si le venv n'existe pas, on lance setup automatiquement
if not exist venv\Scripts\activate.bat (
    echo [!] venv absent. Lancement de setup.bat...
    call setup.bat
    if errorlevel 1 (
        echo [ERREUR] Setup a echoue.
        pause
        exit /b 1
    )
)

echo [i] Activation du venv...
call venv\Scripts\activate.bat
echo [OK] venv actif
echo.

echo [i] Migrations...
python manage.py migrate
if errorlevel 1 (
    echo [ERREUR] Migrations ont echoue.
    pause
    exit /b 1
)

echo [i] Verification du compte admin...
python manage.py shell -c "from accounts.models import User; User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin') if not User.objects.filter(email='admin@transport.bj').exists() else print('  -> admin OK')"
echo.

echo ============================================================
echo   BACKEND EN LIGNE :
echo     API:    http://localhost:8000/api
echo     Admin:  http://localhost:8000/admin/django/
echo     Login:  admin@transport.bj / Admin@12345
echo ============================================================
echo.
echo [i] Cette fenetre affiche les logs en direct.
echo [i] Pour arreter: fermez cette fenetre ou lancez stop.bat
echo.

python manage.py runserver 0.0.0.0:8000

echo.
echo [i] Le serveur s'est arrete.
pause

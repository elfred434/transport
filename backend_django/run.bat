@echo off
REM Lance le serveur Django dans le venv (Windows)
cd /d "%~dp0"

if not exist venv (
    echo [!] Lancez d'abord activ.bat
    pause
    exit /b 1
)

call venv\Scripts\activate.bat
python manage.py migrate
python manage.py shell -c "from accounts.models import User; import sys; 
exists = User.objects.filter(email='admin@transport.bj').exists();
sys.exit(0 if exists else 1)"
if errorlevel 1 (
    python manage.py shell -c "from accounts.models import User; User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin'); print('[+] Compte admin créé : admin@transport.bj / Admin@12345')"
) else (
    echo [i] Compte admin déjà existant
)
echo [+] Démarrage du serveur sur http://localhost:8000
python manage.py runserver 0.0.0.0:8000
pause

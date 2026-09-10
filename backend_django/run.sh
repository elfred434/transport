#!/usr/bin/env bash
# Lance le serveur Django dans le venv (Linux/Mac)
set -e
cd "$(dirname "$0")"
if [ ! -d "venv" ]; then
    echo "venv absent : lancez d'abord ./activ.sh"
    exit 1
fi
. venv/bin/activate
python manage.py migrate
python manage.py shell -c "
from accounts.models import User
if not User.objects.filter(email='admin@transport.bj').exists():
    User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin')
    print('[+] Compte admin créé : admin@transport.bj / Admin@12345')
else:
    print('[i] Compte admin déjà existant')
"
echo "[+] Démarrage du serveur sur http://localhost:8000"
python manage.py runserver 0.0.0.0:8000

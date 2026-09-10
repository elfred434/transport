#!/usr/bin/env bash
# =============================================================
# backend_django/start.sh
# Démarre le serveur Django (runserver) dans le venv sur :8000.
# Usage : bash start.sh
# =============================================================
set -e
cd "$(dirname "$0")"
GREEN='\033[0;32m'; BLUE='\033[0;34m'; RED='\033[0;31m'; NC='\033[0m'

if [ ! -d "venv" ]; then
    echo -e "${RED}[X]${NC} venv absent. Lancez d'abord ./setup.sh"
    exit 1
fi

echo -e "${BLUE}[i]${NC} Activation venv..."
. venv/bin/activate

echo -e "${BLUE}[i]${NC} Vérification migrations..."
python manage.py migrate --run-syncdb 2>&1 | tail -2

# S'assure qu'un admin existe
python manage.py shell -c "
from accounts.models import User
if not User.objects.filter(email='admin@transport.bj').exists():
    User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin')
" 2>/dev/null

# Enregistre le PID pour ./stop.sh
echo $$ > .server.pid

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  Backend Django en ligne :${NC}"
echo -e "    API:        http://localhost:8000/api"
echo -e "    Admin:      http://localhost:8000/admin/django/"
echo -e "    Compte:     admin@transport.bj / Admin@12345"
echo -e "${GREEN}============================================${NC}"
echo -e "${BLUE}[i]${NC} Logs en direct. Arrêtez avec ./stop.sh ou Ctrl+C${NC}"
echo ""

cleanup() {
    rm -f .server.pid
    echo ""
    echo -e "${BLUE}[i]${NC} Serveur arrêté."
}
trap cleanup EXIT INT TERM

exec python manage.py runserver 0.0.0.0:8000

#!/usr/bin/env bash
# =============================================================
# backend_django/setup.sh
# Installe le venv Python + les dépendances du backend Django.
# Usage : bash setup.sh
# =============================================================
set -e
cd "$(dirname "$0")"
GREEN='\033[0;32m'; BLUE='\033[0;34m'; RED='\033[0;31m'; NC='\033[0m'
info() { echo -e "${BLUE}[i]${NC} $*"; }
ok()   { echo -e "${GREEN}[OK]${NC} $*"; }
err()  { echo -e "${RED}[X]${NC} $*"; exit 1; }

command -v python3 >/dev/null 2>&1 || err "Python 3 requis (apt/brew install python3)"

echo ""
echo "=== Setup BACKEND Django ==="
echo ""

if [ ! -d "venv" ]; then
    info "Création du venv (./venv) ..."
    python3 -m venv venv
    ok "venv créé"
else
    ok "venv déjà présent"
fi

info "Activation du venv ..."
. venv/bin/activate
ok "python = $(which python) — $(python --version)"

info "Mise à jour pip ..."
pip install --upgrade pip --quiet

info "Installation dépendances (requirements.txt) ..."
pip install -r requirements.txt
ok "Dépendances installées"

info "Migrations de la base ..."
python manage.py migrate

info "Création du compte admin si absent ..."
python manage.py shell -c "
from accounts.models import User
if User.objects.filter(email='admin@transport.bj').exists():
    print('[OK] Compte admin déjà existant')
else:
    User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin')
    print('[OK] Compte admin créé : admin@transport.bj / Admin@12345')
"
echo ""
ok "=== Backend prêt — lancez ./start.sh pour démarrer ==="

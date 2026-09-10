#!/usr/bin/env bash
# =============================================================
# setup.sh — Installation automatique backend + frontend (Linux/Mac)
# Usage : bash setup.sh
# =============================================================
set -e
cd "$(dirname "$0")"
ROOT="$(pwd)"

# Couleurs
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[i]${NC} $*"; }
ok()      { echo -e "${GREEN}[✓]${NC} $*"; }
warn()    { echo -e "${YELLOW}[!]${NC} $*"; }
err()     { echo -e "${RED}[✗]${NC} $*"; exit 1; }

# ---------- Prérequis ----------
command -v python3  >/dev/null 2>&1 || err "Python 3 est requis (apt install python3 / brew install python)"
command -v node     >/dev/null 2>&1 || err "Node.js est requis (https://nodejs.org)"
command -v npm      >/dev/null 2>&1 || err "npm est requis"

echo ""
echo "============================================"
echo "  Transport.bj — Installation automatique"
echo "============================================"
echo ""

# ---------- 1. BACKEND (Django, venv) ----------
info "=== Backend Django ==="
cd "$ROOT/backend_django"

# Vérifier venv
if [ ! -d "venv" ]; then
    info "Création du venv backend_django/venv ..."
    python3 -m venv venv
    ok "venv créé"
else
    ok "venv déjà présent"
fi

info "Activation du venv..."
. venv/bin/activate
ok "python = $(which python)  ($(python --version))"

info "Mise à jour pip..."
pip install --upgrade pip --quiet

info "Installation des dépendances Python (requirements.txt)..."
pip install -r requirements.txt 2>&1 | tail -3
ok "Dépendances Python installées"

info "Application des migrations..."
python manage.py migrate 2>&1 | tail -2

info "Création du compte admin si absent..."
python manage.py shell -c "
from accounts.models import User
if not User.objects.filter(email='admin@transport.bj').exists():
    User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin')
    print('  -> admin@transport.bj / Admin@12345  CRÉÉ')
else:
    print('  -> compte admin déjà existant')
"
ok "Backend prêt"

# ---------- 2. FRONTEND (React/Vite, npm) ----------
info "=== Frontend React/Vite ==="
cd "$ROOT/frontend"

if [ ! -d "node_modules" ]; then
    info "npm install..."
    npm install
    ok "node_modules installé"
else
    ok "node_modules déjà présent"
fi

if [ -f "package-lock.json" ]; then
    info "Vérification des dépendances (npm ci si lock présent)..."
    # npm ci est plus strict; si ça échoue on retombe sur npm install
    npm ci --prefer-offline 2>&1 | tail -3 || npm install 2>&1 | tail -3
fi

ok "Frontend prêt"

# ---------- Résumé ----------
cd "$ROOT"
echo ""
echo "============================================"
echo "  Installation terminée !"
echo "============================================"
echo ""
echo "Pour DÉMARRER le projet :"
echo ""
echo "  Terminal 1 (backend API Django):"
echo "    bash run.sh"
echo ""
echo "  Terminal 2 (frontend Vite dev server):"
echo "    cd frontend && npm run dev"
echo ""
echo "Pour LANCER LES TESTS E2E (après avoir démarré le backend):"
echo "    source backend_django/venv/bin/activate"
echo "    python tests/test_honnete.py"
echo ""
ok "Bon dev !"

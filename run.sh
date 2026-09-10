#!/usr/bin/env bash
# =============================================================
# run.sh — Démarre backend + frontend (Linux/Mac)
# Usage : bash run.sh            # backend+frontend
#         bash run.sh backend    # seulement backend
#         bash run.sh frontend   # seulement frontend
# =============================================================
set -e
cd "$(dirname "$0")"
ROOT="$(pwd)"

GREEN='\033[0;32m'; BLUE='\033[0;34m'; NC='\033[0m'
info() { echo -e "${BLUE}[i]${NC} $*"; }
ok()   { echo -e "${GREEN}[✓]${NC} $*"; }

WHAT="${1:-all}"

start_backend() {
    info "=== Backend Django (http://localhost:8000/api) ==="
    cd "$ROOT/backend_django"
    if [ ! -d "venv" ]; then
        echo "venv absent — lancez d'abord ./setup.sh"
        exit 1
    fi
    . venv/bin/activate
    python manage.py migrate --run-syncdb 2>&1 | tail -2
    # Crée l'admin si absent (au cas où setup.sh n'aurait pas tourné)
    python manage.py shell -c "
from accounts.models import User
if not User.objects.filter(email='admin@transport.bj').exists():
    User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin')
" 2>/dev/null
    ok "Démarrage backend..."
    exec python manage.py runserver 0.0.0.0:8000
}

start_frontend() {
    info "=== Frontend Vite (http://localhost:5173) ==="
    cd "$ROOT/frontend"
    if [ ! -d "node_modules" ]; then
        info "npm install..."
        npm install
    fi
    ok "Démarrage frontend..."
    exec npm run dev -- --host 0.0.0.0
}

case "$WHAT" in
    backend)  start_backend ;;
    frontend) start_frontend ;;
    all)
        # Lance backend en arrière-plan puis frontend au premier plan
        bash "$0" backend > /tmp/transport_backend.log 2>&1 &
        BACK_PID=$!
        echo "[i] Backend PID=$BACK_PID  (logs: /tmp/transport_backend.log)"
        sleep 2
        bash "$0" frontend
        kill $BACK_PID 2>/dev/null
        ;;
    *)
        echo "Usage: $0 [all|backend|frontend]"; exit 1 ;;
esac

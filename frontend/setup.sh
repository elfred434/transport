#!/usr/bin/env bash
# =============================================================
# frontend/setup.sh
# Installe node_modules via npm (Vite + React).
# Usage : bash setup.sh
# =============================================================
set -e
cd "$(dirname "$0")"
GREEN='\033[0;32m'; BLUE='\033[0;34m'; RED='\033[0;31m'; NC='\033[0m'
info() { echo -e "${BLUE}[i]${NC} $*"; }
ok()   { echo -e "${GREEN}[OK]${NC} $*"; }
err()  { echo -e "${RED}[X]${NC} $*"; exit 1; }

command -v node >/dev/null 2>&1 || err "Node.js requis (https://nodejs.org)"
command -v npm  >/dev/null 2>&1 || err "npm requis"

echo ""
echo "=== Setup FRONTEND React/Vite ==="
echo "node : $(node --version)"
echo "npm  : $(npm --version)"
echo ""

if [ -d "node_modules" ]; then
    ok "node_modules déjà présent"
    info "npm ci (vérifie la cohérence avec package-lock.json)..."
    npm ci --prefer-offline 2>&1 | tail -3 || {
        info "npm ci échoué → npm install..."
        npm install 2>&1 | tail -3
    }
else
    info "npm install..."
    npm install 2>&1 | tail -5
fi
ok "Dépendances frontend installées"
echo ""
ok "=== Frontend prêt — lancez ./start.sh pour démarrer ==="

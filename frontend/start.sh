#!/usr/bin/env bash
# =============================================================
# frontend/start.sh
# Démarre le serveur Vite dev sur :5173.
# Usage : bash start.sh
# =============================================================
set -e
cd "$(dirname "$0")"
GREEN='\033[0;32m'; BLUE='\033[0;34m'; RED='\033[0;31m'; NC='\033[0m'

if [ ! -d "node_modules" ]; then
    echo -e "${RED}[X]${NC} node_modules absent. Lancez d'abord ./setup.sh"
    exit 1
fi

# Récupère le port depuis vite.config si défini, sinon 5173
PORT="5173"
echo $$ > .server.pid

cleanup() {
    rm -f .server.pid
    echo ""
    echo -e "${BLUE}[i]${NC} Frontend arrêté."
}
trap cleanup EXIT INT TERM

echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  Frontend Vite en ligne :${NC}"
echo -e "    URL:  http://localhost:${PORT}"
echo -e "${GREEN}============================================${NC}"
echo -e "${BLUE}[i]${NC} Logs en direct. Arrêtez avec ./stop.sh ou Ctrl+C${NC}"
echo ""

exec npm run dev -- --host 0.0.0.0 --port "${PORT}"

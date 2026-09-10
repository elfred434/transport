#!/usr/bin/env bash
# =============================================================
# start.sh — Démarre backend (8000) et frontend (5173) en parallèle (Linux/Mac)
# Usage : bash start.sh              # les deux
#         bash start.sh backend      # seulement backend
#         bash start.sh frontend     # seulement frontend
# =============================================================
set -e
cd "$(dirname "$0")"
GREEN='\033[0;32m'; BLUE='\033[0;34m'; NC='\033[0m'

WHAT="${1:-all}"
BACK_LOG="/tmp/transport_backend.log"
FRONT_LOG="/tmp/transport_frontend.log"

start_backend() {
    echo -e "${BLUE}[i]${NC} Démarrage BACKEND (port 8000)..."
    bash backend_django/start.sh
}

start_frontend() {
    echo -e "${BLUE}[i]${NC} Démarrage FRONTEND (port 5173)..."
    bash frontend/start.sh
}

case "$WHAT" in
    backend)  start_backend ;;
    frontend) start_frontend ;;
    all)
        echo -e "${BLUE}[i]${NC} Lancement backend + frontend en parallèle..."
        echo -e "${BLUE}[i]${NC} Logs backend : $BACK_LOG"
        echo -e "${BLUE}[i]${NC} Logs frontend: $FRONT_LOG"
        bash backend_django/start.sh > "$BACK_LOG" 2>&1 &
        BACK_PID=$!
        echo -e "${GREEN}[OK]${NC} Backend PID=$BACK_PID"
        sleep 3
        bash frontend/start.sh > "$FRONT_LOG" 2>&1 &
        FRONT_PID=$!
        echo -e "${GREEN}[OK]${NC} Frontend PID=$FRONT_PID"
        echo ""
        echo -e "${GREEN}============================================${NC}"
        echo -e "${GREEN}  En ligne :${NC}"
        echo -e "    API      http://localhost:8000/api"
        echo -e "    Front    http://localhost:5173"
        echo -e "${GREEN}============================================${NC}"
        echo -e "${BLUE}[i]${NC} Arrêtez avec : bash stop.sh"
        echo ""
        # Attend les deux process
        wait $BACK_PID $FRONT_PID 2>/dev/null
        ;;
    *)
        echo "Usage: $0 [all|backend|frontend]"; exit 1 ;;
esac

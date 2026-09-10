#!/usr/bin/env bash
# =============================================================
# frontend/stop.sh
# Arrête le serveur Vite dev lancé par start.sh.
# Usage : bash stop.sh
# =============================================================
cd "$(dirname "$0")"
GREEN='\033[0;32m'; BLUE='\033[0;34m'; NC='\033[0m'

echo -e "${BLUE}[i]${NC} Arrêt du frontend Vite..."

# 1. Via PID enregistré
if [ -f .server.pid ]; then
    PID=$(cat .server.pid)
    if kill -0 "$PID" 2>/dev/null; then
        kill "$PID" 2>/dev/null && sleep 1
        kill -9 "$PID" 2>/dev/null
        echo -e "${GREEN}[OK]${NC} Processus $PID arrêté"
        rm -f .server.pid
        exit 0
    fi
    rm -f .server.pid
fi

# 2. Fallback : tous les node/vite
PIDS=$(pgrep -f "vite" 2>/dev/null || true)
if [ -n "$PIDS" ]; then
    echo "$PIDS" | xargs kill 2>/dev/null
    sleep 1
    echo "$PIDS" | xargs kill -9 2>/dev/null
    echo -e "${GREEN}[OK]${NC} Processus Vite arrêtés : $PIDS"
    exit 0
fi

# 3. Libère port 5173
PIDS=$(lsof -ti:5173 2>/dev/null || true)
if [ -n "$PIDS" ]; then
    echo "$PIDS" | xargs kill -9 2>/dev/null
    echo -e "${GREEN}[OK]${NC} Port 5173 libéré (PID $PIDS)"
    exit 0
fi

echo -e "${BLUE}[i]${NC} Aucun serveur frontend en cours."

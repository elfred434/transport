#!/usr/bin/env bash
# =============================================================
# backend_django/stop.sh
# Arrête proprement le serveur Django lancé par start.sh.
# Usage : bash stop.sh
# =============================================================
cd "$(dirname "$0")"
RED='\033[0;31m'; GREEN='\033[0;32m'; BLUE='\033[0;34m'; NC='\033[0m'

echo -e "${BLUE}[i]${NC} Arrêt du backend Django..."

# 1. Via le PID enregistré par start.sh
if [ -f .server.pid ]; then
    PID=$(cat .server.pid)
    if kill -0 "$PID" 2>/dev/null; then
        kill "$PID" 2>/dev/null && sleep 1
        # Force kill si encore vivant
        kill -9 "$PID" 2>/dev/null
        echo -e "${GREEN}[OK]${NC} Processus $PID arrêté (via .server.pid)"
        rm -f .server.pid
        exit 0
    fi
    rm -f .server.pid
fi

# 2. Fallback : tue tout processus "manage.py runserver" sur le port 8000
PIDS=$(pgrep -f "manage.py runserver" 2>/dev/null || true)
if [ -n "$PIDS" ]; then
    echo "$PIDS" | xargs kill 2>/dev/null
    sleep 1
    echo "$PIDS" | xargs kill -9 2>/dev/null
    echo -e "${GREEN}[OK]${NC} Processus Django arrêtés : $PIDS"
    exit 0
fi

# 3. Fallback : libère le port 8000
PIDS=$(lsof -ti:8000 2>/dev/null || true)
if [ -n "$PIDS" ]; then
    echo "$PIDS" | xargs kill -9 2>/dev/null
    echo -e "${GREEN}[OK]${NC} Port 8000 libéré (PID $PIDS)"
    exit 0
fi

echo -e "${BLUE}[i]${NC} Aucun serveur backend en cours d'exécution."

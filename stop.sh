#!/usr/bin/env bash
# =============================================================
# stop.sh — Arrête backend + frontend (Linux/Mac)
# Usage : bash stop.sh
# =============================================================
cd "$(dirname "$0")"
GREEN='\033[0;32m'; BLUE='\033[0;34m'; NC='\033[0m'

echo -e "${BLUE}[i]${NC} Arrêt des serveurs..."
bash backend_django/stop.sh
bash frontend/stop.sh
echo -e "${GREEN}[OK]${NC} Tous les serveurs sont arrêtés."

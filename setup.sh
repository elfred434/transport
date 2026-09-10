#!/usr/bin/env bash
# =============================================================
# setup.sh — Installation complète backend + frontend (Linux/Mac)
# Délègue vers backend_django/setup.sh et frontend/setup.sh
# Usage : bash setup.sh
# =============================================================
set -e
cd "$(dirname "$0")"
GREEN='\033[0;32m'; BLUE='\033[0;34m'; NC='\033[0m'

echo -e "${BLUE}
============================================
  Transport.bj — Installation complete
============================================${NC}"
echo ""

echo -e "${BLUE}>>> Backend${NC}"
bash backend_django/setup.sh
echo ""

echo -e "${BLUE}>>> Frontend${NC}"
bash frontend/setup.sh
echo ""

echo -e "${GREEN}============================================
  Installation terminee !
  Pour demarrer :  bash start.sh
============================================${NC}"

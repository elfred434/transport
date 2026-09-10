#!/usr/bin/env bash
# Script d'activation du venv pour Linux/Mac
# Usage: source backend_django/activ.sh
set -e
cd "$(dirname "$0")"
if [ ! -d "venv" ]; then
    echo "Création du venv..."
    python3 -m venv venv
    . venv/bin/activate
    pip install --upgrade pip
    pip install -r requirements.txt
else
    . venv/bin/activate
fi
echo "✅ venv activé. Python = $(which python), Django = $(python -c 'import django; print(django.get_version())')"

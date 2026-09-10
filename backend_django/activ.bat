@echo off
REM Script d'activation du venv pour Windows (PowerShell ou cmd)
REM Usage: backend_django\activ.bat
cd /d "%~dp0"

if not exist venv (
    echo [+] Création du venv...
    python -m venv venv
    call venv\Scripts\activate.bat
    python -m pip install --upgrade pip
    pip install -r requirements.txt
) else (
    call venv\Scripts\activate.bat
)

echo [OK] venv activé.
python --version

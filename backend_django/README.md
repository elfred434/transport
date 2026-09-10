# Transport.bj — Backend Django 5 + DRF

Réécriture complète du backend en Django 5 + Django REST Framework.
**Tests E2E : 27/27 OK** (cycle complet : inscription → colis → voyage → approbation → réservation → paiement → livraison → commission 95/5 → retrait → payout).

## Démarrage en 2 commandes

### Linux / Mac
```bash
cd backend_django
./run.sh
```
(Crée le venv automatiquement au premier lancement, installe les dépendances,
applique les migrations, crée le compte admin, puis démarre le serveur sur
http://localhost:8000).

### Windows (PowerShell ou cmd)
```powershell
cd backend_django
run.bat
```

## Utilisation manuelle (si tu veux contrôler chaque étape)

```bash
# 1. Activer le venv
source backend_django/venv/bin/activate      # Linux/Mac
backend_django\venv\Scripts\activate.bat     # Windows

# 2. Installer les dépendances
pip install -r backend_django/requirements.txt

# 3. Migrations + admin
cd backend_django
python manage.py migrate
python manage.py shell -c "from accounts.models import User; User.objects.create_superuser(email='admin@transport.bj', password='Admin@12345', nom='Admin', prenom='Super', role='super_admin')"

# 4. Lancer
python manage.py runserver 0.0.0.0:8000
```

## Accès
- API : http://localhost:8000/api
- Admin Django : http://localhost:8000/admin/django/ (mêmes identifiants)
- Compte admin créé : `admin@transport.bj` / `Admin@12345`

## Tests E2E
```bash
cd ..  # revenir à la racine du projet
python tests/test_honnete.py
# → 27/27 OK
```

## Config .env (optionnel, SQLite par défaut)
```env
APP_DEBUG=true
APP_KEY=une-cle-secrete
DB_CONNECTION=mysql          # sqlite par défaut
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=transport
DB_USERNAME=root
DB_PASSWORD=
KKIAPAY_PUBLIC_KEY=...
KKIAPAY_PRIVATE_KEY=...
KKIAPAY_SECRET_KEY=...
KKIAPAY_SANDBOX=true
KKIAPAY_SKIP_SSL_VERIFY=true
```

## Structure
- `config/` — projet Django (settings, urls, wsgi)
- `accounts/` — auth JWT, User custom, profil
- `shipping/` — métier (colis, voyages, réservations, paiements, suivi, retraits, avis)
- `core/` — réponse API standard, pagination, permissions, exceptions
- `venv/` — environnement virtuel Python (**généré, pas versionné**)

## Fichiers pratiques
- `activ.sh` / `activ.bat` — active le venv (le crée s'il n'existe pas)
- `run.sh` / `run.bat` — active + migrate + admin + lance runserver

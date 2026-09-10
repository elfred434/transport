# Transport.bj

Plateforme de mise en relation entre expéditeurs (clients) et transporteurs,
avec paiement mobile Kkiapay et gestion de livraison.

## Stack
- **Frontend** : React 18 + TypeScript + Vite (icônes FontAwesome)
- **Backend** : Django 5 + Django REST Framework + JWT (SimpleJWT)
- **Tests E2E** : 27/27 verts — `tests/test_honnete.py`

## Démarrage en 2 commandes

### 🪟 Windows
1. Double-clique sur **`setup.bat`** (une seule fois, la première fois)
   - Installe Python venv + dépendances backend dans `backend_django/venv/`
   - Installe `node_modules/` du frontend
   - Crée automatiquement le compte admin : `admin@transport.bj / Admin@12345`
2. Double-clique sur **`run.bat`** → démarre l'API Django sur http://localhost:8000
3. Double-clique sur **`run-frontend.bat`** → démarre Vite sur http://localhost:5173

### 🐧 Linux / 🍎 Mac
```bash
# 1. Installation (une fois)
bash setup.sh

# 2. Démarrage backend + frontend
bash run.sh

# ou seulement un des deux
bash run.sh backend
bash run.sh frontend
```

## URLs
- Frontend : http://localhost:5173
- API : http://localhost:8000/api
- Admin Django : http://localhost:8000/admin/django/
- Compte admin par défaut : **admin@transport.bj** / **Admin@12345**

## Tests E2E (cycle complet automatique)
```bash
# Active le venv backend puis lance :
source backend_django/venv/bin/activate     # Linux/Mac
# backend_django\venv\Scripts\activate.bat  # Windows

python tests/test_honnete.py
# → 27/27 OK attendus (colis→voyage→réservation→paiement→livraison→commission 95/5→retrait→payout)
```

## Structure
```
transport/
├── backend_django/     # Backend Django + DRF
│   ├── config/         # settings, urls
│   ├── accounts/       # auth JWT, utilisateurs
│   ├── shipping/       # métier : colis, voyages, réservations, paiements, retraits…
│   ├── core/           # utilitaires (réponse API, pagination, exceptions)
│   ├── venv/           # environnement virtuel (généré, non versionné)
│   └── manage.py
├── frontend/           # React + Vite (node_modules généré)
├── tests/              # tests E2E
├── setup.sh / setup.bat
├── run.sh / run.bat
└── run-frontend.bat
```

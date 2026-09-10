# Transport.bj

Plateforme de mise en relation expéditeurs ↔ transporteurs, paiement Kkiapay sandbox,
gestion livraison avec commission automatique 95% / 5%.

## Stack
- **Frontend** : React 18 + TypeScript + Vite + FontAwesome
- **Backend** : Django 5 + DRF + JWT (SimpleJWT)
- **Tests E2E** : `tests/test_honnete.py` — 27/27 OK

## Démarrage rapide

### 🪟 Windows

| Action | Script |
|---|---|
| 🛠️ Installer une fois | **`setup.bat`** (double-clic à la racine) |
| ▶️ Démarrer | **`start.bat`** → ouvre 2 fenêtres (backend :8000 + frontend :5173) |
| ⏹️ Arrêter | **`stop.bat`** |

### 🐧 Linux / 🍎 Mac

```bash
bash setup.sh      # installation une seule fois
bash start.sh      # démarre backend + frontend
bash stop.sh       # arrêt
```

## Scripts par dossier

Chaque dossier contient ses propres scripts, que les scripts racine appellent :

### `backend_django/`

| Script (Linux/Mac) | Script (Windows) | Action |
|---|---|---|
| `setup.sh` | `setup.bat` | Crée `venv/`, installe requirements.txt, `migrate`, crée l'admin `admin@transport.bj / Admin@12345` |
| `start.sh` | `start.bat` | Démarre Django sur http://localhost:8000/api |
| `stop.sh` | `stop.bat` | Tue proprement le serveur et libère le port 8000 |

### `frontend/`

| Script (Linux/Mac) | Script (Windows) | Action |
|---|---|---|
| `setup.sh` | `setup.bat` | `npm install` / `npm ci` dans `node_modules/` |
| `start.sh` | `start.bat` | Démarre Vite sur http://localhost:5173 |
| `stop.sh` | `stop.bat` | Tue proprement le serveur Vite et libère le port 5173 |

## URLs
- Frontend : http://localhost:5173
- API REST : http://localhost:8000/api
- Admin Django : http://localhost:8000/admin/django/
- Compte admin par défaut : **admin@transport.bj / Admin@12345**

## Tests E2E
```bash
source backend_django/venv/bin/activate
python tests/test_honnete.py
# → 27/27 attendus (cycle complet colis→voyage→réservation→paiement→livraison→commission→retrait→payout)
```

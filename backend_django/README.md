# Transport.bj — Backend Django 5 + DRF

Réécriture complète du backend en Django 5 + Django REST Framework.
**Tests E2E : 27/27 OK** (cycle complet: inscription → colis → voyage → approbation → réservation → paiement → livraison → commission 95/5 → retrait → payout).

## Démarrage rapide (à la racine du projet)

Voir le README racine (`../README.md`) ou, en résumé :

```bash
# 1. Installation une seule fois
bash ../setup.sh        # Linux/Mac
#  ou double-clic sur  setup.bat  (Windows)

# 2. Démarrer
bash ../run.sh                        # backend + frontend (Linux/Mac)
#  ou double-clic sur run.bat         # backend seul (Windows)
#  puis double-clic sur run-frontend.bat  # frontend (Windows)
```

Le backend écoute sur http://localhost:8000/api
Compte admin auto-créé : `admin@transport.bj` / `Admin@12345`

## Structure
- `config/` — projet Django (settings, urls, wsgi)
- `accounts/` — auth JWT, User custom, profil
- `shipping/` — métier (colis, voyages, réservations, paiements, suivi, retraits, avis)
- `core/` — réponse API standard, pagination, permissions, exceptions
- `venv/` — environnement virtuel Python (généré par `setup.sh`, non versionné)
- `requirements.txt` — dépendances Python
- `manage.py` — entrée Django

## Routes principales
| Méthode | Chemin | Description |
|---|---|---|
| POST | `/api/auth/register` | Inscription |
| POST | `/api/auth/login` | Connexion JWT |
| GET | `/api/auth/me` | Profil courant |
| POST | `/api/colis` | Créer un colis |
| GET | `/api/colis/mine` | Mes colis |
| POST | `/api/voyages` | Proposer un voyage |
| POST | `/api/reservations` | Réserver un colis |
| POST | `/api/paiements/{id}/payer` | Payer un colis |
| POST | `/api/suivi` | Ajouter une étape suivi |
| GET | `/api/suivi/{numero}` | Suivi public |
| POST | `/api/retraits` | Demander un retrait |
| GET | `/api/admin/stats` | Stats admin |
| GET/POST | `/api/admin/colis` | Liste + changement de statut |
| GET | `/api/admin/livraisons` | Demandes de livraison en attente |
| POST | `/api/admin/suivi/{id}/livraison` | Confirmer livraison (commission 95/5) |
| POST | `/api/admin/retraits/{id}/decision` | Payer/rejeter un retrait |
| GET | `/api/admin/wallet` | Solde plateforme |

## Config via `.env` (optionnel, SQLite par défaut)
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

# Transport.bj — Backend Django 5 + DRF

Réécriture complète du backend Laravel/PHP en Django 5 + Django REST Framework.

**API 100% compatible** avec le frontend React existant : mêmes routes,
mêmes réponses `{success, data, error}`, même logique métier (rôles,
commissions 95/5, Kkiapay sandbox, retraits, suivi, avis).

## Résultat des tests E2E

```
27/27 OK — cycle complet testé automatiquement :

Santé API, inscription CLIENT/TRANSPORTEUR, login ADMIN
→ Création colis (poids→prix 4200 XOF)
→ Création voyage
→ Approbation admin colis+voyage
→ Réservation transporteur
→ Acceptation client
→ Paiement (webhook simulé)
→ Suivi colis "En cours" / "Livré"
→ Page admin /admin/livraisons (attentes)
→ Confirmation livraison → commission 95/5 versée (4275/225)
→ Solde transporteur crédité
→ Demande retrait transporteur (min 1000 XOF, solde suffisant)
→ Payout admin
→ Wallet admin débité
→ Avis 5★
→ Suivi public par numero_suivi (sans auth)
→ Message contact
```

## Démarrer

```bash
cd backend_django
pip install -r requirements.txt
python manage.py migrate
python manage.py shell -c "
from accounts.models import User
if not User.objects.filter(email='admin@transport.bj').exists():
    User.objects.create_superuser(
        email='admin@transport.bj', password='Admin@12345',
        nom='Admin', prenom='Super', role='super_admin'
    )
"
python manage.py runserver 0.0.0.0:8000
```

API : http://localhost:8000/api

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

- `config/` — Projet Django (settings, urls, wsgi)
- `accounts/` — Auth JWT, User custom, profil
- `shipping/` — Cœur métier :
  - `models.py` → Colis, Voyage, Reservation, Paiement, SuiviColis, Retrait, Avis, ContactMessage, Transporteur, NotificationAdmin, WalletAdmin
  - `views.py` → Endpoints client/transporteur
  - `admin_views.py` → Endpoints admin/super_admin (stats, approbations, livraisons, commissions, payout Kkiapay)
- `core/` — Utilitaires (réponse API standard, pagination, permissions, exceptions)

## Routes principales (mêmes que l'ancien Laravel)

| Méthode | Chemin | Description |
|---|---|---|
| POST | `/api/auth/register` | Inscription |
| POST | `/api/auth/login` | Connexion (JWT) |
| GET | `/api/auth/me` | Profil courant |
| POST | `/api/colis` | Créer un colis |
| GET | `/api/colis/mine` | Mes colis |
| POST | `/api/voyages` | Proposer un voyage |
| POST | `/api/reservations` | Réserver un colis |
| POST | `/api/paiements/{id}/payer` | Payer (ou webhook Kkiapay) |
| POST | `/api/suivi` | Ajouter une étape suivi |
| GET | `/api/suivi/{numero}` | Suivi public |
| POST | `/api/retraits` | Demander un retrait |
| GET | `/api/admin/stats` | Stats admin |
| GET/POST | `/api/admin/colis` | Liste + approuver/refuser |
| GET | `/api/admin/livraisons` | Demandes de livraison en attente |
| POST | `/api/admin/suivi/{id}/livraison` | Confirmer livraison (95/5) |
| POST | `/api/admin/retraits/{id}/decision` | Payer/rejeter un retrait |
| GET | `/api/admin/wallet` | Solde plateforme |

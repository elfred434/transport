# Transport — Agence de Transport de Colis

Plateforme de mise en relation entre expéditeurs de colis et transporteurs, avec
suivi de livraison, paiement, messagerie et panneau d'administration.

Le projet est séparé en deux applications indépendantes qui communiquent par une
**API REST JSON** :

```
transport/
├── backend/     API PHP (JSON) — authentification par jeton Bearer
├── frontend/    Site HTML/CSS/JS vanilla — consomme l'API via fetch
├── sql/         Migrations de la base de données (à appliquer dans l'ordre)
└── PHPMailer/   Bibliothèque d'envoi d'emails (dépendance du backend)
```

## Prérequis

- PHP 8.1+ (avec `pdo_mysql`, `fileinfo`)
- MariaDB / MySQL
- Un serveur web ou le serveur intégré de PHP

## 1. Base de données

Créez la base puis appliquez les migrations **dans l'ordre** :

```bash
mysql -u root -p -e "CREATE DATABASE transport_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p transport_db < sql/000_schema_base.sql
mysql -u root -p transport_db < sql/001_messages_admin_et_compte_admin.sql
mysql -u root -p transport_db < sql/002_api_tokens.sql
```

- `000_schema_base.sql` — les 12 tables de l'application.
- `001_…` — table `messages_admin` + compte administrateur initial.
- `002_…` — table `api_tokens` (jetons d'authentification, hash SHA-256).

Les migrations sont idempotentes (`IF NOT EXISTS` / insertions conditionnelles).

**Compte admin initial** : `admin@transport.bj` / `Admin@12345`
→ **changez ce mot de passe après la première connexion.**

## 2. Configuration

Le backend se configure par variables d'environnement (valeurs par défaut entre
parenthèses) :

| Variable         | Rôle                                            | Défaut                     |
|------------------|-------------------------------------------------|----------------------------|
| `DB_HOST`        | Hôte de la base                                 | `localhost`                |
| `DB_NAME`        | Nom de la base                                  | `transport_db`             |
| `DB_USER`        | Utilisateur base                                | `root`                     |
| `DB_PASS`        | Mot de passe base                               | *(vide)*                   |
| `CORS_ORIGINS`   | Origines autorisées (séparées par `,`)          | `*`                        |
| `TOKEN_TTL_DAYS` | Durée de vie des jetons (jours)                 | `30`                       |
| `APP_URL`        | URL publique du **backend** (liens uploads)     | `http://localhost:8001`    |
| `FRONTEND_URL`   | URL publique du **frontend** (liens emails)     | `http://localhost:8000`    |
| `SMTP_USER`      | Utilisateur SMTP (reset mot de passe)           | *(vide = mode dev)*        |
| `SMTP_PASS`      | Mot de passe / app password SMTP                | *(vide)*                   |
| `SMTP_HOST`      | Hôte SMTP                                       | `smtp.gmail.com`           |
| `SMTP_PORT`      | Port SMTP                                       | `587`                      |

Sans SMTP configuré, le lien de réinitialisation de mot de passe est renvoyé dans
la réponse (`dev_reset_link`) et journalisé — pratique en développement.

## 3. Démarrage

Deux processus distincts (deux ports) :

```bash
# Backend — API sur le port 8001
php -S 0.0.0.0:8001 -t backend/public backend/public/index.php

# Frontend — site statique sur le port 8000
php -S 0.0.0.0:8000 -t frontend
```

- Frontend : http://localhost:8000
- API : http://localhost:8001/api (racine listant les endpoints)
- Espace admin : http://localhost:8000/admin/login.html

Le frontend détecte automatiquement l'URL de l'API : en local il utilise
`http://localhost:8001`, et derrière un proxy de prévisualisation de type
`{port}-{hôte}` il bascule sur le port `8001` du même hôte (voir
`frontend/js/config.js`).

Les fichiers téléversés sont stockés dans `backend/storage/uploads/` (dossier
ignoré par git) et servis par le backend sur `/uploads/...`.

## Architecture

### Backend (`backend/`)

- `public/index.php` — point d'entrée unique : routage, CORS, service des uploads.
- `src/bootstrap.php` — connexion BDD, helpers JSON, auth Bearer, validation,
  uploads sécurisés (liste blanche MIME via `finfo`, noms aléatoires).
- `src/controllers/*.php` — un contrôleur par domaine
  (`auth`, `users`, `colis`, `suivi`, `voyages`, `messages`, `contact`,
  `paiements`, `avis`, `admin`).

**Conventions de l'API**

- Authentification : jeton Bearer (`Authorization: Bearer <token>`), stocké côté
  client, hashé (SHA-256) en base, expiration à `TOKEN_TTL_DAYS`.
- Réponses : `{ "success": true, "data": … }` ou `{ "success": false, "error": "…" }`.
- Codes HTTP explicites (200, 201, 400, 401, 403, 404, 409, 500).
- ~60 endpoints. `GET /api` renvoie la liste et leur nombre.

**Règles métier principales**

- Prix d'un colis : `max(1000, 1000 + 1000 × poids) × 1.2` (arrondi, recalculé à
  chaque modification).
- Mise en relation : un transporteur réserve un colis approuvé pour l'un de ses
  voyages compatibles (même pays de destination, `poids_max ≥ poids`,
  `date_depart ≥ aujourd'hui`) ; le propriétaire du colis accepte/refuse.
- Suivi : statuts `En attente` → `En cours` → `Livré`. Un « Livré » posé par le
  transporteur crée une **demande de confirmation** ; l'admin confirme ou refuse.
- **Commission** : à la confirmation d'une livraison, le transporteur reçoit
  5 % du `prix_estime` du colis, crédités sur `transporteurs.solde` (transaction).
- Paiement : simulé (carte ou mobile money) ; seules des données masquées sont
  stockées.
- Avis : note 1–5, un seul avis par couple (utilisateur, transporteur), publié
  après modération.

### Frontend (`frontend/`)

- `js/config.js` — résolution de `API_URL` selon l'hôte.
- `js/api.js` — wrapper `fetch` (jeton Bearer, JSON/multipart, `ApiError`,
  redirection sur 401).
- `js/ui.js` — barre latérale, gardes d'authentification/admin, notifications,
  échappement HTML, formatage.
- `css/app.css` — styles partagés.
- Pages publiques : `index`, `login`, `register`, `reset-request`,
  `reset-password`, `contact`.
- Pages authentifiées : `dashboard`, `poster-colis`, `colis`, `colis-detail`,
  `suivi`, `recherche`, `reservation-colis`, `paiement`, `profil`,
  `modifier-profil`, `devenir-transporteur`, `profil-transporteur`,
  `transporteur-stats`, `reponses`, `liste-messagerie`, `messagerie`,
  `messagerie-admin`.
- `admin/` — `login.html`, `index.html` (tableau de bord complet),
  `messagerie.html`.

## Tests

Deux suites (dans `tests/`) couvrent l'ensemble du parcours (authentification,
colis, paiement, voyages, réservations, suivi, commission, messagerie,
modération, autorisations, CORS, sécurité des uploads) :

```bash
python3 tests/test_api.py                    # 77 vérifications API bout-en-bout
node    tests/test_frontend_integration.js   # 25 vérifications frontend↔backend
```

Prérequis : les deux serveurs démarrés (backend `:8001`, frontend `:8000`) et la
base `transport_db` accessible. `tests/test_api.py` vérifie certains états en
base via `sudo -n mariadb transport_db` (adapter si votre accès MySQL diffère).

## Sécurité

- Hachage des mots de passe (`password_hash`), jetons d'API hashés.
- Requêtes préparées partout (protection injection SQL).
- Échappement HTML systématique côté frontend (`UI.esc`).
- Uploads : liste blanche MIME (`finfo`), taille limitée, noms aléatoires,
  service des fichiers avec garde `realpath` (anti path-traversal).
- Séparation des rôles : `require_auth` / `require_admin`, vérifications de
  propriété sur chaque ressource.

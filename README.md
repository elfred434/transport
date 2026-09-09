# Transport — Agence de Transport de Colis

Plateforme de mise en relation entre expéditeurs de colis et transporteurs, avec
suivi de livraison, paiement, messagerie et panneau d'administration.

Le projet est séparé en deux applications indépendantes qui communiquent par une
**API REST JSON** (`{ "success": true, "data": … }` / `{ "success": false, "error": "…" }`,
authentification par jeton Bearer) :

```
transport/
├── backend/     API Laravel 13 (Sanctum) — backend principal
├── frontend/    SPA React 19 + Vite + TypeScript — frontend principal
├── legacy/      Version d'origine archivée (API PHP vanilla, site vanilla, PHPMailer)
├── sql/         Schéma de la base (12 tables métier) — partagé par les deux stacks
└── tests/       Suites d'acceptation (78 vérifications API + 25 vérifications frontend)
```

## Prérequis

- PHP 8.3+ (avec `pdo_mysql`, `fileinfo`) et Composer
- Node.js 20+ et npm
- MariaDB / MySQL

## 1. Base de données

Créez la base puis appliquez les migrations **dans l'ordre** :

```bash
mysql -u root -p -e "CREATE DATABASE transport_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p transport_db < sql/000_schema_base.sql
mysql -u root -p transport_db < sql/001_messages_admin_et_compte_admin.sql
mysql -u root -p transport_db < sql/002_api_tokens.sql
```

- `000_schema_base.sql` — les 12 tables métier de l'application.
- `001_…` — table `messages_admin` + compte administrateur initial.
- `002_…` — table `api_tokens` (jetons de l'API vanilla, conservée pour `legacy/`).

Puis les tables d'infrastructure Laravel (cache, jobs, `personal_access_tokens`) :

```bash
cd backend && php artisan migrate --force
```

> ⚠️ Ne créez jamais la table `users` via une migration Laravel : le schéma
> métier de `sql/000_schema_base.sql` est la seule source de vérité.

**Compte admin initial** : `admin@transport.bj` / `Admin@12345`
→ **changez ce mot de passe après la première connexion.**

## 2. Configuration du backend (`backend/.env`)

Copiez `backend/.env.example` vers `backend/.env`, générez la clé, renseignez la
base. Variables spécifiques à l'application (le reste est du Laravel standard) :

| Variable                  | Rôle                                                | Défaut                          |
|---------------------------|-----------------------------------------------------|---------------------------------|
| `DB_*`                    | Connexion à la base                                 | `transport_db`                  |
| `CORS_ORIGINS`            | Origines autorisées (séparées par `,`)              | `*`                             |
| `TOKEN_TTL_DAYS`          | Durée de vie des jetons (jours)                     | `30`                            |
| `MAX_UPLOAD_SIZE`         | Taille maximale d'upload (octets)                   | `5242880`                       |
| `FRONTEND_URL`            | URL publique du frontend (liens des emails)         | `http://localhost:8000`         |
| `TRANSPORT_STORAGE_PATH`  | Racine du disque des uploads                        | `legacy/backend/storage`        |
| `TRANSPORT_APP_URL`       | Préfixe des URLs de fichiers ; **vide = `/uploads/…` relatifs** | *(vide)*            |
| `SMTP_USER` / `SMTP_PASS` | SMTP (reset mot de passe) ; vides = mode dev        | *(vide)*                        |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_FROM` | Hôte SMTP                         | `smtp.gmail.com` / `587`        |

Sans SMTP configuré, le lien de réinitialisation de mot de passe est renvoyé
dans la réponse (`dev_reset_link`) et journalisé — pratique en développement.

Les uploads vivent dans `legacy/backend/storage/uploads/` (emplacement
historique, ignoré par git) pour que les fichiers déjà en ligne restent servis.
En production, fixez `TRANSPORT_STORAGE_PATH` vers un emplacement dédié.

## 3. Démarrage en développement

```bash
# API Laravel — port 8002
cd backend && php artisan serve --host=0.0.0.0 --port=8002

# SPA React — port 8003 (proxy /api et /uploads vers 127.0.0.1:8002)
cd frontend && npm install && npm run dev
```

- SPA : http://localhost:8003
- API : http://localhost:8002/api (racine listant les ~60 endpoints)
- Espace admin : http://localhost:8003/admin/login

Le navigateur n'appelle que l'origine du SPA (pas de CORS en dev) ; la cible du
proxy se change via `VITE_API_TARGET` dans `frontend/`.

## 4. Production (mono-domaine)

```bash
cd frontend && npm run build    # → frontend/dist/
```

Le serveur web (Caddy/nginx) sert `frontend/dist/` en statique (avec repli SPA
vers `index.html`), reverse-proxy `/api` vers php-fpm/Laravel et `/uploads`
vers la route de distribution. Pensez au worker de queues
(`php artisan queue:work`), à OPcache et à `php artisan config:cache`.

## 5. Tests

Les deux suites valident le contrat complet (authentification, colis, paiement,
voyages, réservations, suivi, commission, messagerie, modération, autorisations,
CORS, sécurité des uploads) — **contre n'importe quel port backend** :

```bash
# API (78 vérifications) — backend Laravel démarré sur 8002
API_BASE=http://127.0.0.1:8002 python3 tests/test_api.py

# Intégration frontend↔API (25 vérifications)
API_PORT=8002 API_EXPECTED=http://localhost:8002 node tests/test_frontend_integration.js
```

`tests/test_api.py` vérifie certains états en base via `sudo -n mariadb
transport_db` (adapter si votre accès MySQL diffère). La suite d'intégration
charge le vrai client JS de `legacy/frontend` (paramétrable via `FRONTEND_DIR`).

## Règles métier principales

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

## Architecture

### Backend (`backend/` — Laravel 13)

- Eloquent + query builder sur les tables existantes (aucune migration métier).
- Contrôleurs par domaine (`Auth`, `Colis`, `Voyages`, `Reservations`, `Suivi`,
  `Paiements`, `Avis`, `Messages`, `Contact`, `Admin`…), exceptions `ApiException`
  avec les messages français exacts de l'API d'origine.
- Jetons Sanctum (hashés en base), garde anti path-traversal sur la distribution
  des uploads, CORS configurable.
- Voir `backend/README.md` pour le détail.

### Frontend (`frontend/` — React 19 + Vite + TS)

- Une route par page du site d'origine (26 pages), mêmes classes CSS
  (Bootstrap 5 + `app.css` repris tel quel), mêmes gardes d'authentification.
- Client API avec enveloppe `{success, data|error}`, 401 → redirection login,
  uploads multipart ; toasts, modales et onglets en état React (mêmes classes).
- Les anciens chemins `*.html` (emails, favoris) sont redirigés vers les routes SPA.
- Voir `frontend/README.md` pour le détail.

### Legacy (`legacy/`)

Version d'origine, archivée mais fonctionnelle — utilisée comme référence de
parité et par la suite d'intégration :

```bash
# API PHP vanilla — port 8001
php -S 0.0.0.0:8001 -t legacy/backend/public legacy/backend/public/index.php
# Site vanilla — port 8000
php -S 0.0.0.0:8000 -t legacy/frontend
```

`legacy/backend` lit ses variables d'environnement comme avant (`DB_*`,
`APP_URL`, `FRONTEND_URL`, `SMTP_*`…) et dépend de `legacy/PHPMailer`.

## Sécurité

- Hachage des mots de passe (`password_hash` / cast `hashed` Eloquent), jetons
  d'API hashés en base (Sanctum).
- Requêtes préparées partout (Eloquent / query builder — protection injection SQL).
- Échappement automatique JSX côté React (pas d'`innerHTML`).
- Uploads : liste blanche MIME (`finfo`), taille limitée, noms aléatoires,
  distribution via route dédiée avec garde `realpath` (anti path-traversal).
- Séparation des rôles : middleware `auth:sanctum` / `admin`, vérifications de
  propriété sur chaque ressource.

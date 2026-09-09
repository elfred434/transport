# Transport — Agence de Transport de Colis

Plateforme de mise en relation entre expéditeurs de colis et transporteurs, avec
suivi de livraison, paiement, messagerie et panneau d'administration.

Le projet est séparé en deux applications indépendantes qui communiquent par une
**API REST JSON** (`{ "success": true, "data": … }` / `{ "success": false, "error": "…" }`,
authentification par jeton Bearer) :

```
transport/
├── backend/     API Laravel 13 (Sanctum)
├── frontend/    SPA React 19 + Vite + TypeScript
├── sql/         Schéma de la base (12 tables métier + messages_admin + admin)
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
```

- `000_schema_base.sql` — les 12 tables métier de l'application.
- `001_…` — table `messages_admin` + compte administrateur initial.

Puis les tables d'infrastructure Laravel (cache, jobs, `personal_access_tokens`) :

```bash
cd backend && php artisan migrate --force
```

> ⚠️ Ne créez jamais la table `users` via une migration Laravel : le schéma
> métier de `sql/000_schema_base.sql` est la seule source de vérité.

**Compte admin initial** : `admin@transport.bj` / `Admin@12345`
→ **changez ce mot de passe après la première connexion.**

## 2. Configuration du backend (`backend/.env`)

Copiez `backend/.env.example` vers `backend/.env`, générez la clé
(`php artisan key:generate`), renseignez la base. Variables spécifiques à
l'application (le reste est du Laravel standard) :

| Variable                  | Rôle                                                | Défaut                          |
|---------------------------|-----------------------------------------------------|---------------------------------|
| `DB_*`                    | Connexion à la base                                 | `transport_db`                  |
| `CORS_ORIGINS`            | Origines autorisées (séparées par `,`)              | `*`                             |
| `TOKEN_TTL_DAYS`          | Durée de vie des jetons (jours)                     | `30`                            |
| `MAX_UPLOAD_SIZE`         | Taille maximale d'upload (octets)                   | `5242880`                       |
| `FRONTEND_URL`            | URL publique du frontend (liens des emails)         | `http://localhost:8003`         |
| `TRANSPORT_STORAGE_PATH`  | Racine du disque des uploads (dossier `uploads/`)   | `backend/storage`               |
| `TRANSPORT_APP_URL`       | Préfixe des URLs de fichiers ; **vide = `/uploads/…` relatifs** | *(vide)*            |
| `SMTP_USER` / `SMTP_PASS` | SMTP (reset mot de passe) ; vides = mode dev        | *(vide)*                        |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_FROM` | Hôte SMTP                         | `smtp.gmail.com` / `587`        |

Sans SMTP configuré, le lien de réinitialisation de mot de passe est renvoyé
dans la réponse (`dev_reset_link`) et journalisé — pratique en développement.

Les fichiers uploadés vivent dans `backend/storage/uploads/` (ignoré par git).
En production, fixez `TRANSPORT_STORAGE_PATH` vers un emplacement dédié hors du
dépôt (ex. `/var/www/transport-storage`).

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
CORS, sécurité des uploads) :

```bash
# API (78 vérifications) — backend Laravel démarré sur 8002
API_BASE=http://127.0.0.1:8002 python3 tests/test_api.py

# Intégration frontend↔API (25 vérifications) — charge le vrai client
# React (frontend/src/lib/api.ts, bundlé via rolldown) dans un navigateur simulé
API_BASE=http://localhost:8002 node tests/test_frontend_integration.js
```

`tests/test_api.py` vérifie certains états en base via `sudo -n mariadb
transport_db` (adapter si votre accès MySQL diffère). La suite d'intégration
nécessite `cd frontend && npm install` (fournit rolldown).

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

- Eloquent + query builder sur les tables de `sql/` (aucune migration métier).
- Contrôleurs par domaine (`Auth`, `Colis`, `Voyages`, `Reservations`, `Suivi`,
  `Paiements`, `Avis`, `Messages`, `Contact`, `Admin`…), exceptions `ApiException`
  avec des messages d'erreur lisibles en français.
- Jetons Sanctum (hashés en base), garde anti path-traversal sur la distribution
  des uploads, CORS configurable.
- Voir `backend/README.md` pour le détail.

### Frontend (`frontend/` — React 19 + Vite + TS)

- Une route par page du site (26 pages), Bootstrap 5 + FontAwesome + `app.css`.
- Client API avec enveloppe `{success, data|error}`, 401 → redirection login,
  uploads multipart ; toasts, modales et onglets en état React.
- Les anciens chemins `*.html` (emails, favoris) sont redirigés vers les routes SPA.
- Voir `frontend/README.md` pour le détail.

## Sécurité

- Hachage des mots de passe (cast `hashed` Eloquent), jetons d'API hashés en
  base (Sanctum).
- Requêtes préparées partout (Eloquent / query builder — protection injection SQL).
- Échappement automatique JSX côté React (pas d'`innerHTML`).
- Uploads : liste blanche MIME (`finfo`), taille limitée, noms aléatoires,
  distribution via route dédiée avec garde `realpath` (anti path-traversal).
- Séparation des rôles : middleware `auth:sanctum` / `admin`, vérifications de
  propriété sur chaque ressource.

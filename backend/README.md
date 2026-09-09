# Backend Laravel — API de la plateforme de transport

API de la plateforme (Laravel 13.x, Sanctum), refactorisée depuis l'API PHP
vanilla d'origine (supprimée du dépôt). Elle expose **exactement le même
contrat** : mêmes 60 routes sous `/api/...`, mêmes
verbes (GET/POST/DELETE), même enveloppe de réponse :

```json
{"success": true,  "data": ...}
{"success": false, "error": "message lisible"}
```

## Principes de la migration

- **Base de données existante conservée** : les 12 tables métier (`users`, `colis`,
  `voyages`, ...) sont mappées par Eloquent sans aucune migration de schéma ni de
  données. Seules les tables d'infrastructure Laravel (`personal_access_tokens`,
  `cache`, `jobs`, ...) ont été ajoutées.
- **Jetons d'API** : Sanctum (`personal_access_tokens`, empreinte sha256,
  expiration 30 jours via `TOKEN_TTL_DAYS`) remplace la table `api_tokens`.
  Le contrat client est inchangé : `{token, user}` à la connexion, en-tête
  `Authorization: Bearer <token>`.
- **Uploads** : même liste blanche (JPEG/PNG/GIF/WEBP vérifiés sur le contenu
  réel), mêmes noms aléatoires, même disque physique (`TRANSPORT_STORAGE_PATH`,
  par défaut `backend/storage`, fichiers dans `backend/storage/uploads/`) —
  servis via `GET /uploads/{path}` (garde realpath anti path-traversal, Content-Type
  forcé par l'extension).
- **Comportements historiques préservés** (vérifiés par `tests/test_api.py`) :
  prix `round(max(1000, 1000+1000*poids)*1.2, 2)`, commission transporteur 5 %
  à la confirmation de livraison, `POST /api/voyages` fait passer transporteur
  atomiquement, un « Livré » posé par un transporteur crée une demande de
  confirmation admin, les messages sont stockés bruts (échappement à
  l'affichage), `nom`/`prenom`/`telephone` sont échappés à l'insertion.

## Lancer en développement

```bash
composer install
cp .env.example .env && php artisan key:generate
# renseigner DB_* puis :
php artisan migrate --force   # tables d'infrastructure uniquement
php artisan serve --host=0.0.0.0 --port=8002
```

Suite d'acceptation (depuis la racine du dépôt) :

```bash
API_BASE=http://127.0.0.1:8002 python3 tests/test_api.py   # → 78/78
```

## Structure

| Emplacement | Rôle |
|---|---|
| `routes/api.php` | les 60 routes métier (+ racine `/api`) |
| `routes/web.php` | distribution gardée des uploads (`/uploads/{path}`) |
| `app/Http/Controllers/` | `AuthController` + `Api/*` (un contrôleur par domaine) |
| `app/Models/` | Eloquent mappé sur les tables existantes (horodatages métier : `date_post`, `date_envoi`, ...) |
| `app/Support/` | `ApiResponse` (enveloppe), `Pricing` (règles tarifaires), `Uploads`, `Files` (URL publiques), `In` (entrées) |
| `app/Exceptions/ApiException.php` | erreur métier (message + code HTTP), rendue en JSON |
| `config/transport.php` | paramètres métier (TTL jetons, limites, listes de statuts) |

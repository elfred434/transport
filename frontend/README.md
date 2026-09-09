# Frontend React — Agence de Transport de Colis

SPA React 19 + Vite + TypeScript qui remplace le frontend vanilla (`../legacy/frontend`,
26 pages). Même identité visuelle (Bootstrap 5, FontAwesome, `app.css` repris
tel quel) et même contrat d'API (`{success, data|error}`, jeton Bearer
`transport_token` en localStorage).

## Architecture

| Emplacement | Rôle |
|---|---|
| `src/lib/api.ts` | client API (port de `js/api.js`) : enveloppe, 401 → /login, upload multipart |
| `src/lib/format.tsx` | `money/date/datetime`, `StatusBadge`, `SmartImg`, `Stars` (port de `UI.*`) |
| `src/context/AuthContext.tsx` | session (`/api/auth/me`), login/logout (port de `UI.requireUser`) |
| `src/components/Toasts.tsx` | notifications (port de `UI.toast`, mêmes classes CSS) |
| `src/components/Layouts.tsx` | `PublicLayout` (topbar/footer), `AppLayout` (sidebar), `AdminGate` |
| `src/pages/` | une page = une page du frontend vanilla (voir table de routage dans `App.tsx`) |

## Développement

```bash
npm install
npm run dev          # http://localhost:8003
```

Le backend attendu est l'API Laravel sur le port **8002**
(`cd ../backend && php artisan serve --port=8002`).
`/api` et `/uploads` sont proxyfiés vers lui par Vite (`VITE_API_TARGET` pour
changer de cible) : le navigateur n'appelle que l'origine du SPA, sans CORS.
Les URLs de fichiers renvoyées par l'API sont relatives (`/uploads/...`).

## Production

```bash
npm run build        # → dist/ (statiques à servir par Caddy/nginx)
```

En production mono-domaine, le serveur web sert `dist/` et reverse-proxy
`/api` + `/uploads` vers php-fpm/Laravel (voir runbook Phase 3).

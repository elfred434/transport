# Frontend React — Agence de Transport de Colis

SPA React 19 + Vite + TypeScript — le frontend de la plateforme (26 pages,
refactorisé depuis le site vanilla d'origine, supprimé du dépôt). Même identité
visuelle (Bootstrap 5, FontAwesome, `app.css` repris tel quel) et même contrat
d'API (`{success, data|error}`, jeton Bearer `transport_token` en localStorage).

## Architecture

| Emplacement | Rôle |
|---|---|
| `src/lib/api.ts` | client API : enveloppe, 401 → /login, upload multipart |
| `src/lib/format.tsx` | `money/date/datetime`, `StatusBadge`, `SmartImg`, `Stars` |
| `src/context/AuthContext.tsx` | session (`/api/auth/me`), login/logout, gardes |
| `src/components/Toasts.tsx` | notifications (mêmes classes CSS que le site d'origine) |
| `src/components/Layouts.tsx` | `PublicLayout` (topbar/footer), `AppLayout` (sidebar), `AdminGate` |
| `src/pages/` | une page = une page du site d'origine (voir table de routage dans `App.tsx`) |

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

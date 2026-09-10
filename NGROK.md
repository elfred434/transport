# Configuration ngrok pour tests locaux

## URLs actuelles de l'utilisateur
- Backend : https://e820-137-255-185-221.ngrok-free.app (port 8002)
- Frontend : https://cf1b-137-255-185-221.ngrok-free.app (port 5173)

## Backend `backend/.env` - VERSION CORRIGÉE (sans espaces)

```env
APP_URL=https://e820-137-255-185-221.ngrok-free.app
FRONTEND_URL=https://cf1b-137-255-185-221.ngrok-free.app
CORS_ORIGINS=https://cf1b-137-255-185-221.ngrok-free.app,https://e820-137-255-185-221.ngrok-free.app,http://localhost:5173,http://localhost:8003
TRANSPORT_APP_URL=https://e820-137-255-185-221.ngrok-free.app
```

**Erreurs corrigées :**
- ❌ `APP_URL=https://...app ` (espace à la fin) → ✅ sans espace
- ❌ `TRANSPORT_APP_URL=https://...app ` (espace) → ✅ sans espace
- ❌ `CORS_ORIGINS=https://e820...` (seulement backend) → ✅ doit inclure frontend `cf1b`
- ❌ `VITE_API_TARGET=[https://...](https://...)` (markdown) → ✅ URL brute

## Frontend `frontend/.env`

```env
VITE_API_TARGET=https://e820-137-255-185-221.ngrok-free.app
```

## Frontend `vite.config.ts` - VERSION CORRIGÉE

```ts
server: {
  host: '0.0.0.0',
  port: 5173,
  allowedHosts: ['.e2b.app', '.ngrok-free.app', '.ngrok.io', 'localhost'],
  proxy: {
    '/api': {
      target: process.env.VITE_API_TARGET || 'http://127.0.0.1:8002',
      changeOrigin: true,
      headers: { 'ngrok-skip-browser-warning': 'true' }
    },
    '/uploads': {
      target: process.env.VITE_API_TARGET || 'http://127.0.0.1:8002',
      changeOrigin: true,
      headers: { 'ngrok-skip-browser-warning': 'true' }
    }
  }
}
```

**Erreurs corrigées :**
- ❌ `allowedHosts: ['.e2b.app']` seulement → ✅ ajoute `.ngrok-free.app`
- ❌ Pas de header ngrok → ✅ `ngrok-skip-browser-warning: true` dans proxy

## Client API `frontend/src/lib/api.ts`

Ajout du header pour éviter la page d'avertissement ngrok free :

```ts
headers['ngrok-skip-browser-warning'] = 'true'
```

## Commandes de lancement

```bash
# Terminal 1 - Backend
cd backend
php artisan config:clear
php artisan serve --host=0.0.0.0 --port=8002

# Terminal 2 - Frontend
cd frontend
npm run dev -- --host 0.0.0.0 --port 5173

# Terminal 3 - Ngrok backend
ngrok http 8002 --host-header=rewrite

# Terminal 4 - Ngrok frontend
ngrok http 5173 --host-header=rewrite
```

## Tests depuis l'extérieur

```bash
# Test API avec header ngrok
curl -H "ngrok-skip-browser-warning: true" https://e820-137-255-185-221.ngrok-free.app/api

# Tests automatisés
API_BASE=https://e820-137-255-185-221.ngrok-free.app python3 tests/test_api.py
```

## Pourquoi `ngrok-skip-browser-warning` ?

Ngrok free affiche une page d'avertissement HTML si le header `User-Agent` est un navigateur standard.
Solutions :
1. Header `ngrok-skip-browser-warning: true` (utilisé ici)
2. User-Agent custom
3. Compte ngrok payant

Sans ce header, les requêtes fetch du frontend échouent (reçoivent HTML au lieu de JSON).

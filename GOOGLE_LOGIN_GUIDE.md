# Guide Connexion Google One Tap – Transport

**Choix utilisateur :** One Tap (Identity Services) + rôle par défaut `client`

## 1. Créer projet Google Cloud

1. Va sur https://console.cloud.google.com/
2. **Nouveau projet** → Nom `SPIISTMOVE Transport` → Créer
3. Menu **APIs & Services → OAuth consent screen**
   - User Type : **External**
   - App name : `SPIISTMOVE`
   - User support email : ton email
   - Developer contact : ton email
   - Save & Continue (scopes : laisser `email`, `profile`, `openid`)
   - Test users : ajoute ton email perso pour test

4. Menu **APIs & Services → Credentials**
   - **Create Credentials → OAuth Client ID**
   - Application type : **Web application**
   - Name : `Transport Web`
   - **Authorized JavaScript origins :**
     ```
     http://localhost:8003
     http://127.0.0.1:8003
     https://4e0d-137-255-185-221.ngrok-free.app
     https://e820-137-255-185-221.ngrok-free.app
     ```
   - **Authorized redirect URIs :**
     ```
     http://localhost:8002/auth/google/callback
     http://127.0.0.1:8002/auth/google/callback
     https://e820-137-255-185-221.ngrok-free.app/auth/google/callback
     ```
   - Create → copie **Client ID** (`.apps.googleusercontent.com`) et **Client Secret**

## 2. Configurer backend Laravel

Dans `backend/.env` :
```
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxx
GOOGLE_REDIRECT_URI=https://e820-137-255-185-221.ngrok-free.app/auth/google/callback
```

Exécuter migration Google :
```bash
mysql -u root -p transport_db < sql/003_google_login.sql
# ou si déjà fait : ALTER TABLE users MODIFY role ENUM('utilisateur','client','transporteur','admin','super_admin')
# + ADD google_id, provider, avatar
```

Le endpoint `POST /api/auth/google/one-tap` est déjà en place (public) :
- Body `{ credential: id_token }` (JWT Google)
- Vérifie via `https://oauth2.googleapis.com/tokeninfo?id_token=...`
- Vérifie `aud == GOOGLE_CLIENT_ID`
- Cherche user par `google_id` ou `email`, sinon crée avec role `client`
- Retourne `{token, user}` comme login normal (Sanctum)

## 3. Configurer frontend React

Dans `frontend/.env` :
```
VITE_GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
VITE_API_TARGET=https://e820-137-255-185-221.ngrok-free.app
VITE_KKIAPAY_PUBLIC_KEY=95ab811a8947a69d7d57d3a9ffdc8f9ae3abb4d
VITE_KKIAPAY_SANDBOX=true
```

Le composant `src/components/GoogleOneTap.tsx` :
- Charge `https://accounts.google.com/gsi/client`
- `google.accounts.id.initialize({client_id, callback})`
- `renderButton` dans Login et Register
- Callback envoie `credential` à `/api/auth/google/one-tap` → `setToken` → redirect dashboard

Pages modifiées :
- `Login.tsx` → inclut `<GoogleOneTap mode="login" />`
- `Register.tsx` → inclut `<GoogleOneTap mode="register" />`

## 4. Tester

1. `cd backend && php artisan config:clear`
2. `cd frontend && npm install && npm run dev`
3. Va sur `/login` → bouton Google "Se connecter avec Google" doit apparaître
4. Clique → choisis compte → doit te connecter et créer user si nouveau

## 5. Sécurité

- Vérif `aud` et `email_verified`
- Mot de passe aléatoire pour users Google (pas utilisable en login classique)
- `google_id` unique, `provider=google`
- Token Sanctum 30 jours comme login normal

## 6. Prochaines étapes (optionnel)

- Ajouter `GOOGLE_CLIENT_SECRET` pour flow Socialite redirect classique (`/auth/google` → callback) si tu veux aussi le flow redirect
- Ajouter lien "Associer Google" dans `/profil`
- Permettre choix rôle après Google signup (actuellement client par défaut comme demandé)

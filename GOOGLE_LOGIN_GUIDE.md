# Guide : configurer la connexion Google (One Tap) + Kkiapay

## 1. Kkiapay (paiements & payouts)

Les clés se récupèrent sur **https://dashboard.kkiapay.com/#/developers**.
Tu as 3 clés : **Public**, **Private**, **Secret**.

| Où | Variable |
|---|---|
| `backend_django/.env` | `KKIAPAY_PUBLIC_KEY=…`, `KKIAPAY_PRIVATE_KEY=…`, `KKIAPAY_SECRET_KEY=…` |
| `frontend/.env.local` | `VITE_KKIAPAY_PUBLIC_KEY=…` (la même clé publique que ci-dessus) |

Laisse `KKIAPAY_SANDBOX=true` tant que tu n'es pas en production.
Laisse `KKIAPAY_SKIP_SSL_VERIFY=true` pour éviter l'erreur cURL 60 sur Windows.

Si les 3 clés sont vides, le backend bascule automatiquement en mode simulation (aucun vrai paiement, aucun vrai payout) — c'est le mode dans lequel les tests 27/27 passent aujourd'hui.

## 2. Google OAuth — Créer les identifiants

1. Va sur https://console.cloud.google.com/
2. Crée un **projet** (ex: "Transport.bj")
3. Menu → **APIs & Services** → **Écran de consentement OAuth**
   - Externe → Remplis nom ("Transport.bj"), email de support, email de contact dev
   - Ajoute les scopes : `openid`, `email`, `profile`
   - En "Test users", ajoute tes propres comptes Gmail si app en mode test
4. Menu → **APIs & Services** → **Identifiants** → **Créer des identifiants** → **ID client OAuth 2.0**
   - Type d'application : **Application Web**
   - Nom : "Transport.bj Web"
   - **Origines JavaScript autorisées** :
     ```
     http://localhost:5173
     http://127.0.0.1:5173
     https://880a-xxxx.ngrok-free.app      <-- remplace par ton URL ngrok actuelle
     https://ton-domaine-de-prod.com
     ```
   - **URI de redirection autorisés** (même si tu utilises One Tap, Google en a besoin) :
     ```
     http://localhost:5173/auth/google/callback
     https://880a-xxxx.ngrok-free.app/auth/google/callback
     ```
5. Crée → une popup affiche **ton Client ID** (du style `xxxxxx.apps.googleusercontent.com`). Copie-le.
   - Le **Client Secret** n'est pas nécessaire pour One Tap (tu peux le garder quand même).

## 3. Coller la clé dans les .env

### `backend_django/.env`
```env
GOOGLE_CLIENT_ID=xxxxxx.apps.googleusercontent.com
# GOOGLE_CLIENT_SECRET=xxxxxxxxxxxx   # optionnel, pour flux OAuth classique
GOOGLE_REDIRECT_URI=http://localhost:5173/auth/google/callback
GOOGLE_SKIP_SSL_VERIFY=true
GOOGLE_ALLOW_JWT_FALLBACK=false        # à passer à false en PROD (force vérif Google)
```

### `frontend/.env.local`
```env
VITE_GOOGLE_CLIENT_ID=xxxxxx.apps.googleusercontent.com
```

⚠️ **La valeur doit être IDENTIQUE** dans les deux fichiers (le backend vérifie l'audience du JWT).

## 4. Redémarrer les serveurs

```powershell
# Terminal backend
cd C:\...\transport\backend_django
.\stop.bat
.\start.bat

# Terminal frontend
cd C:\...\transport\frontend
# Ctrl+C puis
npm run dev
```

Fais un **Ctrl+Shift+R** sur la page login/register : le bouton Google doit apparaître.

## 5. Quand tu ajoutes une nouvelle URL ngrok

À chaque fois que tu redémarres ngrok, l'URL change. Tu dois :
1. Récupérer la nouvelle URL ngrok (ex: `https://abcd-123-...ngrok-free.app`)
2. L'ajouter dans la console Google → Identifiants → ton Client ID → **Origines JavaScript autorisées**
3. Attendre 2-3 minutes (propagation)
4. Hard refresh du navigateur

## 6. Mode dev / simulation

- `APP_DEBUG=true` + `GOOGLE_ALLOW_JWT_FALLBACK=true` : si l'appel HTTPS à Google échoue (cURL 60/SSL, réseau coupé, etc.), le backend décode le JWT en local sans vérifier la signature. C'est pratique en dev mais **À DÉSACTIVER EN PROD** (`GOOGLE_ALLOW_JWT_FALLBACK=false`).
- Quand les clés Kkiapay sont absentes, tout paiement/payout est simulé (aucun argent ne circule).

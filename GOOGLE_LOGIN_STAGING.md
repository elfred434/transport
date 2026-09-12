# Guide : configurer la connexion Google (One Tap) sur staging

Le code Google Login est **déjà prêt** en frontend et backend (bouton Google sur Login/Register, endpoint `/api/auth/google/one-tap`, CSP, CORS, etc.). Il ne manque qu'une chose : ton **Google Client ID OAuth 2.0** à brancher dans Vercel + Render.

## Étape 1 — Créer le Client ID (3 min)

1. Ouvre https://console.cloud.google.com/apis/credentials (connecté avec ton compte Google)
2. En haut, sélectionne/Crée un projet → `SpiistMove` (nom libre)
3. Menu gauche → **Écran de consentement OAuth**
   - User Type → **Externe** → Créer
   - Remplis :
     - Nom de l'application : `SpiistMove`
     - E-mail d'assistance utilisateur : ton email
     - E-mail de contact développeur : ton email
   - **Ajouter des domaines** (optionnel en mode test) : laisse vide
   - Enregistrer
4. Menu gauche → **Identifiants** → **+ CRÉER DES IDENTIFIANTS** → **ID client OAuth 2.0**
   - Type d'application : **Application Web**
   - Nom : `SpiistMove Web`
   - **Origines JavaScript autorisées** (clique "+ AJOUTER UN ÉLÉMENT") :
     ```
     http://localhost:5173
     http://127.0.0.1:5173
     https://transport-elfred434-5736s-projects.vercel.app
     ```
   - **URI de redirection autorisés** (même pour One Tap, Google en a besoin) :
     ```
     http://localhost:5173/auth/google/callback
     https://transport-elfred434-5736s-projects.vercel.app/auth/google/callback
     ```
   - ⚠️ **Important** : ajoute aussi les URL de preview Vercel si tu veux tester les PR (optionnel)
5. **Créer** → une popup affiche :
   - **Votre ID client** : `XXXXXXXXXXXX.apps.googleusercontent.com`
   - **Votre code secret client** : tu peux l'ignorer (One Tap n'en a pas besoin)
6. Copie l'**ID client** (`XXXX.apps.googleusercontent.com`) et colle-le ici dans le chat.

## Étape 2 — Je branche la clé et redéploie

Dès que tu me donnes le Client ID, je :

1. L'injecte dans Vercel (var `VITE_GOOGLE_CLIENT_ID`) et Render (var `GOOGLE_CLIENT_ID`)
2. Redéploie les deux services
3. Teste le bouton Google end-to-end

## Étape 3 — Mise en production Google (passage en "Publié")

Par défaut ton app Google est en mode **Test** : seuls les comptes ajoutés comme "Test users" peuvent se connecter. Quand tu veux ouvrir à tout le monde :

1. Console Google → Écran de consentement OAuth → **PUBLIER L'APPLICATION**
2. Cela active la connexion Google pour tous les comptes Gmail.

## Notes techniques

- **One Tap** : popup Google automatique en haut à droite + bouton "Se connecter avec Google"
- Le backend vérifie le JWT Google auprès de `oauth2.googleapis.com/tokeninfo`
- L'audience est vérifiée (`aud == GOOGLE_CLIENT_ID`)
- L'email doit être vérifié par Google (sinon refus)
- Si un compte existe déjà avec le même email, il est lié au Google ID
- Sinon un nouveau compte client est créé automatiquement avec nom/prénom/photo depuis Google
- En **staging/développement local**, tu peux ajouter ton propre email comme "utilisateur test" pour te connecter même si l'app n'est pas publiée.

# 🚀 Guide de déploiement TEST (gratuit)

Stack : **Neon PostgreSQL** (BDD serverless gratuite) + **Django sur Render** (backend gratuit) + **React sur Vercel** (frontend gratuit).
Kkiapay reste en mode **SANDBOX** pour les tests (pas de vrai débit).

---

## Étape 0 — Créer les comptes (tous gratuits)

- **GitHub** : tu as déjà le repo `elfred434/transport` ✅
- **Neon.tech** : base de données PostgreSQL serverless gratuite à vie (512 MB, jamais en veille).
  Inscris-toi avec GitHub → https://console.neon.tech
- **Render.com** : héberge le backend Django (plan Free 750h/mois = suffisant pour tester).
  Inscris-toi avec GitHub → https://dashboard.render.com
- **Vercel.com** : héberge le frontend React (gratuit, HTTPS auto, redéploie à chaque push).
  Inscris-toi avec GitHub → https://vercel.com

---

## Étape 1 — Créer la base de données sur Neon

1. Connecte-toi sur https://console.neon.tech
2. Clique **Create a project**
   - **Project name** : `spiistmove`
   - **Region** : choisis **Europe (Frankfurt)** — le plus proche du Bénin pour de bonnes latences.
   - **Postgres version** : défaut (16).
3. Clique **Create project**.
4. Après 30 secondes tu arrives sur la page de connexion.
5. Dans le bloc **Connection Details** :
   - **Database** : `neondb` (par défaut)
   - **Role/Password** : copiés auto
   - **Host** : `ep-xxx.eu-central-1.aws.neon.tech`
   - Clique sur **Pooled connection** ? Non, garde **Direct connection** (copie le bouton **URL** ou la chaîne `postgres://...`).
   - **Copie l'URL complète** (bouton "Copy" à côté du lien) → elle ressemble à :
     ```
     postgres://spiistmove_owner:xxxxxxxxxxxxxxxxxxxxx@ep-xxx.eu-central-1.aws.neon.tech/neondb?sslmode=require
     ```
6. **Garde cette URL sous la main** : c'est `DATABASE_URL` que tu colleras plus tard dans Render.

---

## Étape 2 — Déployer le backend sur Render

1. Va sur https://dashboard.render.com → **New + → Web Service**
2. Connecte ton compte GitHub et sélectionne le repo `elfred434/transport`
3. Remplis le formulaire :

   | Champ | Valeur |
   |---|---|
   | **Name** | `spiistmove-api` (libre à toi, ça donne le sous-domaine `spiistmove-api.onrender.com`) |
   | **Region** | **Frankfurt (EU Central)** (même que Neon pour la latence) |
   | **Branch** | `main` |
   | **Root Directory** | ⚠️ **`backend_django`** (obligatoire : le backend n'est pas à la racine du repo) |
   | **Runtime** | **Python 3** |
   | **Build Command** | `./build.sh` |
   | **Start Command** | `gunicorn config.wsgi:application --bind 0.0.0.0:$PORT --workers 2 --timeout 120` |
   | **Instance Type** | **Free** |

4. Ouvre **Advanced → Add Environment Variable** et ajoute TOUTES ces variables :

   | Clé | Valeur |
   |---|---|
   | `APP_DEBUG` | `false` |
   | `PYTHON_VERSION` | `3.12.0` |
   | `KKIAPAY_SANDBOX` | `true` |
   | `KKIAPAY_SKIP_SSL_VERIFY` | `false` |
   | `APP_NAME` | `SpiistMove` |
   | `DATABASE_URL` | **colle l'URL Neon copiée à l'étape 1** (commençant par `postgres://` et finissant par `?sslmode=require`) |
   | `DEFAULT_FROM_EMAIL` | `test@spiistmove.com` (les mails s'afficheront dans les logs tant que tu n'auras pas configuré SMTP) |
   | `APP_KEY` | clique **Generate** |
   | `SECRET_KEY` | clique **Generate** (tu peux mettre la même chose que APP_KEY) |
   | `APP_FRONTEND_URL` | **laisse vide pour l'instant** (on la remplira après le frontend) |
   | `CORS_ALLOWED_ORIGINS` | **laisse vide aussi** (l'APP_FRONTEND_URL sera ajoutée automatiquement en CORS) |

5. Clique **Create Web Service**.
6. Attends ~3-5 minutes (logs qui défilent : `pip install` → `collectstatic` → `migrate`).
   ⚠️ Si tu vois `django.db.utils.OperationalError: could not connect` → vérifie que l'URL DATABASE_URL est bien complète et qu'elle finit par `?sslmode=require`. Neon impose SSL.
7. Quand la bulle en haut passe au **vert "Live"**, ouvre l'URL :
   `https://spiistmove-api.onrender.com/api/`
   Tu dois voir un JSON :
   ```json
   {"success": true, "data": {"name":"SpiistMove — API", ...}}
   ```
8. **Note cette URL backend** (ex: `https://spiistmove-api.onrender.com`).

> 💡 Le plan Free Render s'endort après 15 min d'inactivité, mais la BDD Neon reste active. Le premier chargement après une pause peut prendre 30-60s, c'est normal.

---

## Étape 3 — Déployer le frontend sur Vercel

1. Va sur https://vercel.com/new → importe le repo `transport` (clique **Import**).
2. Configure le projet :

   | Champ | Valeur |
   |---|---|
   | **Project Name** | `spiistmove` (donne `spiistmove.vercel.app`) |
   | **Framework Preset** | Vite (auto-détecté) |
   | **Root Directory** | ⚠️ **`frontend`** (obligatoire) |
   | **Build Command** | `npm run build` |
   | **Output Directory** | `dist` |
   | **Install Command** | `npm install` |

3. Clique **Environment Variables** et ajoute :

   | Clé | Valeur |
   |---|---|
   | `VITE_API_BASE_URL` | **l'URL backend Render** de l'étape 2, ex: `https://spiistmove-api.onrender.com` (SANS `/` à la fin) |
   | `VITE_KKIAPAY_SANDBOX` | `true` |

4. Clique **Deploy**. Attends ~1 minute.
5. Quand c'est prêt, Vercel te félicite avec une coche ✨. Clique sur l'URL `https://spiistmove.vercel.app`.
6. **Note cette URL frontend**.

---

## Étape 4 — Connecter les deux (CORS)

Retourne sur Render → service `spiistmove-api` → menu **Environment** → **Add Environment Variable** :

   | Clé | Valeur |
   |---|---|
   | `APP_FRONTEND_URL` | l'URL Vercel de l'étape 3, ex: `https://spiistmove.vercel.app` |

Clique **Save Changes** → Render redéploie automatiquement (~1-2 min).

L'URL frontend est automatiquement ajoutée à la liste blanche CORS, pas besoin de mettre `CORS_ALLOWED_ORIGINS`.

---

## Étape 5 — Tester

1. Ouvre `https://spiistmove.vercel.app` dans ton navigateur.
2. Clique **S'inscrire** → crée un compte client.
3. Poste un colis (tu peux uploader une photo pour vérifier que le stockage fonctionne).
4. Déconnecte-toi, crée un compte **transporteur**.
5. Teste la recherche, la réservation, etc.
6. Le **paiement Kkiapay reste en sandbox** — aucune somme n'est réellement débitée. Utilise un numéro de test sandbox (généralement `+229 61 00 00 00` ou les cartes de test Kkiapay).
7. Teste sur téléphone avec la même URL.

---

## Étape 6 — Créer un compte admin (optionnel)

1. Rends-toi sur le dashboard Render → service `spiistmove-api` → bouton **Shell** en haut à droite.
2. Une fois le shell ouvert :
   ```bash
   python manage.py createsuperuser
   ```
3. Réponds aux questions :
   - Email : ton email
   - Nom / Prénom
   - Password (8 caractères minimum)
4. Tu peux ensuite :
   - Soit te connecter avec ce compte sur l'URL Vercel, puis modifier le rôle en base de données (via shell : `u.role='admin'; u.save()`).
   - Soit plus simple : va sur https://console.neon.tech → ton projet → **SQL Editor** :
     ```sql
     UPDATE users SET role = 'super_admin' WHERE email = 'ton-email@test.com';
     ```
5. Déconnecte-toi / reconnecte-toi sur Vercel : tu devrais voir le bouton **Admin** dans la sidebar.

---

## Étape 7 — Emails et reset mot de passe

Pour l'instant (sans SMTP), les emails de réinitialisation de mot de passe ne sont pas envoyés. Pour tester le reset :
1. Sur Render → service backend → **Logs**
2. Fais "Mot de passe oublié" sur le site → dans les logs tu verras le lien de reset (format console)
3. Copie le lien `/reset-password?token=...` et colle-le après ton URL Vercel :
   `https://spiistmove.vercel.app/reset-password?token=xxx`

Pour activer les vrais emails plus tard, crée un compte **Brevo** (gratuit, 300 mails/jour) puis ajoute sur Render :
- `EMAIL_HOST` = `smtp-relay.brevo.com`
- `EMAIL_PORT` = `587`
- `EMAIL_HOST_USER` / `EMAIL_HOST_PASSWORD` (clés SMTP Brevo)
- `EMAIL_USE_TLS` = `true`
- `DEFAULT_FROM_EMAIL` = ton adresse d'expéditeur

---

## Mises à jour

Désormais, **à chaque fois que tu feras `git push` sur `main`** :
- Render re-build et redéploie automatiquement le backend (~1-2 min)
- Vercel re-build et redéploie automatiquement le frontend (~30s)
Tu n'as rien d'autre à faire.

---

## Débogage rapide

| Problème | Solution |
|---|---|
| Page blanche au 1er chargement | Le backend Render s'est endormi : attends 30-60s et rafraîchis. |
| Erreur CORS dans la console | Vérifie que `APP_FRONTEND_URL` sur Render contient bien l'URL Vercel **exacte** (avec `https://`, sans `/` final). Attends le redéploiement. |
| Erreur 500 lors du POST colis | Ouvre Render → Logs : regarde la stack trace Django pour voir le problème. |
| `SSL required` ou `psycopg2 error` | Vérifie que DATABASE_URL finit par `?sslmode=require` (Neon impose SSL). |
| Redirection vers /login aléatoire | Le token a expiré ou la session est corrompue : vide le localStorage du navigateur et reconnecte-toi. |

---

## Quand tu passeras en production "vraie" (clients payants)

1. **Kkiapay** : passe `KKIAPAY_SANDBOX=false` et colle les 3 clés **live** dans Render (`KKIAPAY_PUBLIC_KEY`, `KKIAPAY_PRIVATE_KEY`, `KKIAPAY_SECRET_KEY`).
2. **Backend Render** : passe en plan **Starter** ($7/mois) pour ne plus s'endormir et avoir 512 MB de RAM.
3. **Neon** : upgrade si nécessaire (le plan gratuit 512MB suffit pour plusieurs milliers d'utilisateurs).
4. **Nom de domaine** : achète un `.com` / `.bj`, connecte-le à Vercel (domaine principal), ajoute le domaine sur Render, mets à jour `APP_FRONTEND_URL`, `CORS_ALLOWED_ORIGINS` et `ALLOWED_HOSTS`.
5. **HTTPS** : automatique via Vercel + Render (Let's Encrypt).

# 🚀 Guide de déploiement TEST (gratuit)

Stack : **Backend Django sur Render** + **Frontend React sur Vercel** + **PostgreSQL gratuit (Render)**.
Kkiapay reste en mode **SANDBOX** pour les tests.

---

## Étape 1 — Créer les comptes (gratuits)

- **GitHub** : tu as déjà le repo `elfred434/transport`, c'est bon.
- **Render.com** : s'inscrire avec GitHub (gratuit).
- **Vercel.com** : s'inscrire avec GitHub (gratuit).

---

## Étape 2 — Backend sur Render

1. Va sur https://dashboard.render.com → **New + → Web Service**
2. Connecte ton compte GitHub et sélectionne le repo `transport`
3. **Important** : dans "Root Directory", mets **`backend_django`**
4. Remplis :
   - **Name** : `spiistmove-api` (ce sera le sous-domaine `spiistmove-api.onrender.com`)
   - **Runtime** : Python 3
   - **Build Command** : `./build.sh`
   - **Start Command** : `gunicorn config.wsgi:application --bind 0.0.0.0:$PORT --workers 2 --timeout 120`
   - **Plan** : **Free** (750h/mois — suffisant pour tester)
5. Ouverte la section **Advanced → Add Environment Variable** et ajoute :

   | Clé | Valeur |
   |-----|--------|
   | `APP_DEBUG` | `false` |
   | `APP_KEY` | clique **Generate** pour générer une valeur aléatoire |
   | `SECRET_KEY` | clique **Generate** (même valeur que APP_KEY, ou différente) |
   | `APP_NAME` | `SpiistMove` |
   | `KKIAPAY_SANDBOX` | `true` |
   | `KKIAPAY_SKIP_SSL_VERIFY` | `false` |
   | `PYTHON_VERSION` | `3.12.0` |
   | `INSTALL_MYSQL` | (ne pas mettre cette variable) |
   | `CORS_ALLOWED_ORIGINS` | laisse vide pour l'instant, on remplira après le frontend |
   | `APP_FRONTEND_URL` | laisse vide pour l'instant |
   | `DEFAULT_FROM_EMAIL` | `test@spiistmove.com` (les mails iront dans la console Render si pas de SMTP) |

6. Ne crée pas la BDD manuellement :
   - Après déploiement, va dans le dashboard du service → **PostgreSQL** → **Add PostgreSQL** (gratuit)
   - Render injectera automatiquement `DATABASE_URL`, les migrations tourneront au prochain deploy.
7. Clique **Create Web Service**. Attends 3-5 minutes (build + migrate).
8. Quand c'est fini, ouvre `https://spiistmove-api.onrender.com/api/` dans ton navigateur : tu dois voir le JSON `{"success": true, "data": {...}}`.

### URL du backend
Note-la : `https://spiistmove-api.onrender.com` (adaptée au nom que tu as choisi).

> ⚠️ Le plan Free de Render se met en veille après 15 min d'inactivité. Le premier chargement peut prendre 30-60 secondes le temps que le serveur démarre.

---

## Étape 3 — Frontend sur Vercel

1. Va sur https://vercel.com/new → importe le repo `transport`
2. **Important** : dans "Root Directory", mets **`frontend`**
3. Framework Preset : **Vite** (auto-détecté)
4. Build Command : `npm run build`
5. Output Directory : `dist`
6. **Environment Variables** :

   | Clé | Valeur |
   |-----|--------|
   | `VITE_API_BASE_URL` | `https://spiistmove-api.onrender.com` (URL de l'étape 2, avec le bon nom) |
   | `VITE_KKIAPAY_SANDBOX` | `true` |

7. Clique **Deploy**. Attends ~1 minute.
8. Vercel te donne une URL du style `spiistmove.vercel.app`. Note-la.

---

## Étape 4 — Connecter les deux entre eux

1. Retourne sur **Render** → le service `spiistmove-api` → **Environment** → **Add Environment Variable**
2. Ajoute/modifie :
   - `APP_FRONTEND_URL` = `https://spiistmove.vercel.app` (URL Vercel)
   - `CORS_ALLOWED_ORIGINS` = `https://spiistmove.vercel.app`
3. Sauvegarde → Render redéploie automatiquement (~2min).

## Étape 5 — Premier test

1. Ouvre `https://spiistmove.vercel.app` dans ton navigateur
2. Crée un compte client
3. Poste un colis — le paiement restera en mode SANDBOX (pas de vrai débit)
4. Vérifie que tu peux réserver en tant que transporteur depuis un autre compte
5. Teste sur téléphone avec la même URL.

---

## Étape 6 — Si tu veux tester les emails (optionnel)

En sandbox sans SMTP, les liens de réinitialisation de mot de passe sont visibles dans les logs Render :
- Sur Render → service `spiistmove-api` → **Logs**
- Cherche la ligne `Subject: SpiistMove — Réinitialisation` ou `Content-Type: text/plain` → tu verras le lien `/reset-password?token=xxx`
- Copie-colle le lien après ton URL Vercel : `https://spiistmove.vercel.app/reset-password?token=xxx`

Pour activer de vrais emails plus tard, ajoute sur Render les variables SMTP :
- `EMAIL_HOST` (ex: `smtp-relay.brevo.com`, `smtp.gmail.com`)
- `EMAIL_PORT` (`587`)
- `EMAIL_HOST_USER` / `EMAIL_HOST_PASSWORD`
- `EMAIL_USE_TLS=true`
- `DEFAULT_FROM_EMAIL`

---

## Commandes utiles (si tu dois débuguer)

Depuis le dashboard Render → **Shell** (ou via SSH) :
```bash
python manage.py createsuperuser     # créer un admin
python manage.py showmigrations      # vérifier que les migrations sont passées
python manage.py dbshell             # accès console SQL (PostgreSQL)
```

Depuis ton PC :
```bash
cd transport
git push                              # n'importe quel push déclenche un redéploiement auto (Vercel + Render rebuildent)
```

---

## Passer en production vraie plus tard

Quand tu voudras ouvrir au public :
1. Passer Kkiapay en live : `KKIAPAY_SANDBOX=false`, remplir les 3 clés Kkiapay
2. Configurer un vrai SMTP (Brevo gratuit = 300 mails/jour)
3. Passer le backend Render en plan **Starter** ($7/mois, pas de mise en veille)
4. Acheter un nom de domaine, le connecter sur Vercel (frontend) et ajouter le domaine dans `ALLOWED_HOSTS` + `CORS_ALLOWED_ORIGINS`

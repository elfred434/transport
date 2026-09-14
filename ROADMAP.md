# SpiistMove — Roadmap des features à implémenter

> Triées par priorité (impact utilisateur / sécurité / métier).  
> ✅ = fait, 🔧 = en cours, ❌ = à faire.

---

## 🔴 PRIORITÉ HAUTE (stabilisation & sécurité)

| # | Feature | État | Notes |
|---|---|---|---|
| 1 | Vérification email à l'inscription | ✅ | 6 chiffres, lockout 5 essais, resend antispam |
| 2 | Brute-force login/verify-email | ✅ | Verrou 15 min par compte |
| 3 | CSP frontend stricte | ✅ | |
| 4 | JWT en cookies HttpOnly | ✅ | Nettoyage localStorage au chargement |
| 5 | CORS whitelisté prod | ✅ | localhost retiré |
| 6 | Énumération emails | ✅ | login/register/reset tous neutres |
| 7 | JWKS Google Sign-In | ✅ | Plus de fallback non signé en prod |
| 8 | Générateur de mot de passe fort | ✅ | Sur Register + ResetPassword |
| 9 | **Double facteur (2FA) par email/TOTP pour admins** | ✅ (email) | Code 6 chiffres par email à chaque connexion admin (10 min, 5 essais). TOTP pourra être ajouté plus tard. |
| 10 | **Audit trail (logs de sécurité)** | ✅ | Table `security_events` : connexions, échecs, verrous, webhooks, erreurs 500, fraude. IP+UA enregistrés. |
| 11 | **Rate limiting global (Redis)** | ❌ | Laissé de côté (throttling DRF par défaut suffit pour l'instant, multi-workers avec cache en mémoire). |
| 12 | **Webhooks Kkiapay avec vérification de signature** | ✅ | Refuse tout webhook Kkiapay non signé (401) ou sans clé configurée (410 deprecated, FedaPay a remplacé). |
| 13 | **Détection de fraude paiement** | ✅ (niveau 1) | Règles non bloquantes : payout > 200k XOF, >10 payouts/paiements par IP/heure, cumul > 200k/h → flag + log `fraud_flag` ; email alerte à l'admin. |
| 14 | **Alertes email admin en cas d'erreur critique** | ✅ | `AdminAlertMiddleware` : log 500 + email à ADMINS rate-limité 5 min. |
| 15 | **Validation téléphone internationale robuste** | ✅ | `phonenumbers` (libphonenumber) : parse/valide BJ, CI, TG, SN, BF, ML, NE + international. Format E.164 en base. |
| 16 | **Compte admin superadmin bootstrap DÉSACTIVÉ en prod** | ✅ | Renvoie 410 Gone + log WARNING à tout appel si ALLOW_BOOTSTRAP≠true. Activable uniquement le temps de créer l'admin. |

---

## 🟠 PRIORITÉ MOYENNE (métier & conversion)

### Expérience utilisateur
| # | Feature | État | Notes |
|---|---|---|---|
| 17 | Inscription / connexion Google One Tap | ✅ | |
| 18 | Réinitialisation mot de passe | ✅ | |
| 19 | Page vérification email (6 cases) | ✅ | |
| 20 | **Indicateur de force du mot de passe EN DIRECT pendant la saisie** | ✅ | Barre 0-5 (longueur/min/maj/chiffre/symbole) sur Register + ResetPassword |
| 21 | **Connexion persistante (Remember me)** | ✅ | Checkbox « Se souvenir de moi (30j) » → refresh_token max_age=30 jours + email pré-rempli |
| 22 | **Validation email en temps réel (format, MX)** | ✅ (format) | Barre de statut en direct + endpoint `/api/auth/check-email` (anti-énumération : toujours ok:true) |
| 23 | **Téléphone avec indicatif pays (+229 par défaut)** | ✅ | `PhoneInput` léger avec 9 pays UEMOA+FR+Autre, sort en E.164 |
| 24 | **Vérification de numéro de téléphone par OTP SMS** | ❌ | Termidor, Twilio ou Wave SMS pour Bénin |
| 25 | **Connexion par code SMS (passwordless)** | ❌ | Alternative pour utilisateurs qui n'ont pas d'email sous la main |
| 26 | **Rôles transporteur avec pièce d'identité et validation** | ❌ | Upload carte d'identité, plaque véhicule, validation manuelle par admin avant d'accepter des colis |
| 27 | **Géolocalisation carte OpenStreetMap** | ❌ | Sélection pick-up/drop-off avec autocomplétion |
| 28 | **Notifications push/email lors de changement statut colis** | ❌ | |
| 29 | **Temps estimé de livraison + prix avant validation** | ❌ | Calcul en fonction distance/poids |
| 30 | **Calculatrice de prix publique** | ❌ | Sans compte, avec dimension/poids/distance |
| 31 | **Messagerie temps réel (WebSocket)** | ❌ | Remplacer le polling par Django Channels ou Pusher/Ably |
| 32 | **Photos / tracking colis (preuve livraison)** | ❌ | Photo + signature électronique du réceptionniste |
| 33 | **Système de notation / avis étoilés** | ❌ | Les clients notent les transporteurs (0-5 étoiles + commentaire) |
| 34 | **Système de litiges** | ❌ | Ouvrir un ticket si colis perdu/abîmé |
| 35 | **Historique de mes colis/recherches** | ❌ | Filtres, exports PDF/CSV |
| 36 | **Mode sombre / choix de langue (FR/EN)** | ❌ | i18n avec react-i18next |
| 37 | **Référencement SEO (balises meta, sitemap, Open Graph)** | ❌ | React Helmet, pages d'atterrissage pour les villes béninoises |
| 38 | **PWA (installation app mobile)** | ❌ | manifest.json + service worker pour mise en cache offline |
| 39 | **Application mobile React Native / Flutter** | ❌ | Plus tard, réutiliser l'API |

### Paiements & rémunération
| # | Feature | État | Notes |
|---|---|---|---|
| 40 | Paiement Kkiapay | ✅ | Mode sandbox |
| 41 | **Commission 95%/5% automatique à la livraison** | ❌ | Aujourd'hui la commission est-elle appliquée ? Vérifier + automatiser virement instantané transporteur (ou demande retrait) |
| 42 | **Wallet transporteur + retraits Kkiapay/Moov/MTN** | 🔧 | Routes retraits existent mais à tester bout-en-bout en prod |
| 43 | **Solde bloqué vs disponible** | ❌ | Le montant d'un colis en cours doit être "bloqué" jusqu'à livraison |
| 44 | **Historique des transactions avec export** | ❌ | PDF reçu fiscal pour transporteurs |
| 45 | **Kkiapay mode production** | ❌ | Passer de `sandbox=true` à `false` après tests complets |
| 46 | **Autres moyens de paiement** (Moov Money, MTN Mobile Money, Wave, Orange Money) | ❌ | Intégrations directes ou via un agrégateur (Kkiapay en gère déjà certains) |
| 47 | **Remboursements** | ❌ | Annulation de colis + remboursement automatique |

---

## 🟡 PRIORITÉ BASSE (améliorations)

### Administration
| # | Feature | État | Notes |
|---|---|---|---|
| 48 | Dashboard admin avec statistiques | ✅ (basique) | Graphiques (Chart.js ou Recharts) |
| 49 | Gestion CRUD utilisateurs | ✅ (basique) | |
| 50 | **Recherche/filtres avancés** sur colis, utilisateurs, paiements | ❌ | |
| 51 | **Modération des avis / messages** | ❌ | Blacklist mots, cacher messages inappropriés |
| 52 | **Rôles intermédiaires** (support client, gestionnaire, etc.) | ❌ | Aujourd'hui seulement client/transporteur/admin/super_admin |
| 53 | **Dashboard transporteur dédié** | ✅ (basique) | |
| 54 | **Tableau de bord analytique (CA, colis livrés/annulés, taux rétention)** | ❌ | |
| 55 | **Export Excel/CSV des données** | ❌ | |
| 56 | **Impression étiquettes colis PDF** | ❌ | QR code + numéro de suivi |

### Technique / DevOps
| # | Feature | État | Notes |
|---|---|---|---|
| 57 | Variables d'env par environnement | ✅ | |
| 58 | Mise en place de Sentry (frontend + backend) | ❌ | Monitoring erreurs en prod |
| 59 | **Sauvegarde BDD Neon automatique quotidienne + rétention 30j** | ❌ | À activer sur Neon |
| 60 | **Environnement de staging séparé** (base de données non-production) | ❌ | Aujourd'hui les mêmes identifiants Vercel pointent prod → risque de fausses données |
| 61 | **Tests unitaires et E2E (Playwright)** | ❌ | Tests critiques : login, register+verify, paiement |
| 62 | **CI/CD GitHub Actions** : tests + lint avant déploiement | ❌ | Éviter les déploiements avec des migrations cassées |
| 63 | **Compression gzip/brotli** sur Render | ✅ (gunicorn+whitenoise) | |
| 64 | **Lazy loading / code splitting** du bundle JS | ❌ | Bundle à ~560 KB, splitter par route |
| 65 | **Optimisations images uploadées** | ❌ | Redimensionnement + compression avant stockage ; stockage sur S3/Cloudinary/R2 (pas sur le disque Render qui est éphémère) |
| 66 | **Stockage fichiers** (photos de profil, photos colis) | ❌ | Render disk est éphémère → brancher AWS S3 / Cloudflare R2 / Supabase Storage |
| 67 | **Sitemap + robots.txt + meta SEO dynamiques** | ❌ | |
| 68 | **Changelog / page "À propos" / CGU / politique de confidentialité** | ❌ | Obligatoire légalement en France/Bénin |
| 69 | **RGPD** : export/suppression des données personnelles | ❌ | Page "Mes données" + endpoint DPO |
| 70 | **Rate-limit global par IP avec Redis** | ❌ | cf. point 11 |

---

## 🧪 Checklist avant mise en production publique

- [ ] Retirer le endpoint `/api/bootstrap/superadmin` une fois l'admin créé
- [ ] Passer `Kkiapay` en mode production (clé publique + secrète, sandbox=false)
- [ ] Vérifier manuellement le flux complet : inscription → code email → vérification → post-colis → paiement → livraison → paiement transporteur
- [ ] Monter la clé secrète Django `SECRET_KEY` en aléatoire long (pas la valeur `django-insecure-change-me-...`)
- [ ] Désactiver `APP_DEBUG` en prod (déjà fait)
- [ ] Brancher les backups Neon
- [ ] Sentry ou équivalent connecté
- [ ] Page CGU/mentions légales/confidentialité
- [ ] Compte email de support fonctionnel (contact@transportcolis.com existe ?)
- [ ] Test de charge avec k6/locust sur les endpoints critiques (login, register, kkiapay webhook)

---

## 🎯 Focus immédiat recommandé (prochains jours)

1. **Webhooks Kkiapay avec vérification de signature** — risque de faux paiements si quelqu'un POST `/api/webhooks/kkiapay` à la main.
2. **Suppression définitive du bootstrap superadmin** une fois ton compte admin créé.
3. **Vérification téléphonique OTP SMS** pour les transporteurs (et pour confirmer les comptes).
4. **Stockage des fichiers sur S3/R2** (Render free perd les fichiers à chaque redéploiement).
5. **Sentry** — voir les erreurs des vrais utilisateurs.
6. **Tests E2E Playwright** sur le flux critique.


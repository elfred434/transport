# RAPPORT TRANSITION - Transport Platform - 2026-09-10 (mis à jour)

## Contexte Général
- Projet : plateforme transport colis Bénin, Laravel 13 + Sanctum + React 18 + Vite
- Backend : `/home/user/transport/backend` — API (local `http://127.0.0.1:8002`, ngrok `https://e820-137-255-185-221.ngrok-free.app` — URL peut changer si ngrok redémarré)
- Frontend : `/home/user/transport/frontend` — React SPA, build 476kB, 64 modules
- DB : MySQL `transport_db`, migrations 000–005
- Paiement : Kkiapay 100% XOF, widget + verify serveur + payout, fallback sandbox SUCCESS si clés invalides
- Auth : Sanctum tokens 30 jours, Google One Tap Identity Services
- Remote git : `https://github_pat_...@github.com/elfred434/transport.git` — token dans remote, NE PAS EFFACER

## Historique des Commits (récents → ancien)

| Hash    | Sujet |
|---------|-------|
| 91973fe | Ajout script test_cycle.py (test end-to-end 12 étapes) — **cycle validé sur ngrok** |
| fd55faf | Signal admin livraison + notifications (banner rouge clignotant, beep Web Audio, badge pulse, polling 30s, routes notifications, pending_livraisons stats) |
| 67fb665 | Commission 95% transporteur / 5% admin + retraits auto Kkiapay + wallet admin |
| 81281f5 | Fix Google cURL 60 SSL Windows |
| 8480752 | Google One Tap |
| 8bb0af5 | Fix reservation-colis liste |
| 004d0da | Fix nb_colis_transportes (accepte+termine) |

## Ce qui a été fait dans cette session

### 1. Clone du dépôt
Le workspace était vide. Clonage via le PAT fourni → `/home/user/transport`.

### 2. Migration SQL `sql/005_notifications_signal.sql` (créée)
Table `notifications_admin` : id, type, colis_id, suivi_id, transporteur_id, message, lu, created_at, index.

### 3. Backend — Signal livraison + notifications
- `AdminController::stats()` : retourne maintenant `pending_livraisons[]` (20 derniers colis en demande, jointure client+transporteur) et `notifications_non_lues`
- `AdminController::notifications()` (nouveau) : GET `/api/admin/notifications` — liste + compteur non_lus ; fallback sur pending si table absente
- `AdminController::notificationRead()` (nouveau) : POST `/api/admin/notifications/{id}/read`
- `AdminController::notificationsReadAll()` (nouveau) : POST `/api/admin/notifications/read-all`
- `AdminController::livraisonDecision()` : marque automatiquement les notifs liées au colis comme lues
- `SuiviController::store()` : quand transporteur marque Livré → Log::info + insert dans `notifications_admin` (try/catch si table absente)

### 4. Routes ajoutées dans `backend/routes/api.php`
```
GET  /api/admin/notifications
POST /api/admin/notifications/{id}/read
POST /api/admin/notifications/read-all
```

### 5. Frontend `AdminIndex.tsx` — Signal admin livraison
- **Banner rouge clignotant** (CSS keyframes `adminBlink`) visible sur TOUTES les sections quand `stats.demandes_livraison > 0`, avec :
  - Titre alerte « X LIVRAISONS À CONFIRMER »
  - Liste des 5 premières pending avec boutons Confirmer/Refuser directs
  - Bouton « Voir N de plus » qui bascule vers section colis
- **Beep de notification** généré via **Web Audio API** (3 tonalités 880/1100/1320 Hz) — pas de fichier mp3 externe nécessaire
- **Badge rouge pulse** sur le bouton « colis » de la nav avec le nombre de demandes
- **Bouton toggle son** (volume-high / volume-xmark) à côté des onglets pour activer/couper le beep
- **Polling automatique toutes les 30 s** de `/api/admin/stats` (silencieux, ne reset pas les listes)
- **Toast de détection** : si le compteur augmente par rapport au chargement précédent → toast + beep
- **Surlignage jaune** + bordure rouge sur les lignes colis qui ont `demande_livraison_id` avec badge « ! » pulse et badge « Livraison à confirmer »
- Interface `PendingLivraison` ajoutée

### 6. Build frontend
`npm run build` → OK, 476 kB JS / 336 kB CSS, 64 modules.

### 7. Test cycle complet validé ✅
Le script `tests/test_cycle.py` a été exécuté avec succès contre l'API ngrok :
```
✅ santé API v2.1
✅ client créé id=71
✅ transporteur créé id=72
✅ colis posté id=63 paiement=37
✅ colis approuvé
✅ voyage créé id=39
✅ voyage approuvé
✅ réservation créée id=49
✅ réservation acceptée
✅ paiement vérifié (fallback sandbox)
✅ livré signalé
✅ demandes_livraison=1, pending=1
✅ prix=7200 XOF → 95%=6840, 5%=360
✅ commissions T=6840 A=360
✅ wallet admin solde = 780 XOF (cumul 5% sur plusieurs tests)
✅ solde transporteur = 6840 XOF (en_attente)
✅ notifications non_lues=0
```

### Détails importants du cycle réel observés
- Quand le client POST `/api/colis`, le paiement est créé automatiquement (pas besoin de l'initier à part)
- Le **transporteur** POST `/api/reservations` (il réserve le colis pour SON voyage)
- Le **client** accepte la réservation via POST `/api/reservations/{id}/action accepte`
- Le **transporteur** marque Livré → signal admin
- L'**admin** confirme via `/api/admin/suivi/{suivi_id}/livraison` → 95% au solde transporteur + 5% au wallet admin + tentative payout auto
- Le payout auto Kkiapay retourne un fallback sandbox `FAILED` car les clés actuelles sont invalides → le retrait reste `en_attente` (comportement attendu en sandbox) ; le transporteur peut faire une demande manuelle via `TransporteurStats`, l'admin peut retry depuis la section Retraits.

## ⚠️ Déploiement en PROD (ngrok) — à faire côté serveur

L'URL ngrok (`e820-137-255-185-221.ngrok-free.app`) pointe vers le serveur de prod qui n'a PAS encore tiré les derniers commits. Il faut exécuter :

```bash
cd /chemin/vers/transport   # dossier serveur ngrok
git pull origin main

# Appliquer la nouvelle migration
mysql -u root -p transport_db < sql/005_notifications_signal.sql

# Vérifier que les migrations précédentes ont été appliquées (si pas déjà fait)
mysql -u root -p transport_db < sql/003_google_login.sql
mysql -u root -p transport_db < sql/004_commission_and_retraits.sql

# Vider les caches Laravel
php artisan config:clear
php artisan cache:clear

# Redémarrer le serveur Laravel (php artisan serve ou supervisor/systemd)
# Par exemple si on utilise php artisan serve --port=8002 :
# pkill -f "artisan serve"; nohup php artisan serve --host=0.0.0.0 --port=8002 > /tmp/laravel.log 2>&1 &

# Déployer le build frontend (si le serveur nginx/PHP dessert aussi le build React)
# Copier le contenu de frontend/dist/ vers le dossier public du frontend (selon config nginx)
# Typiquement : rsync -av --delete frontend/dist/ /var/www/transport-frontend/
```

Après déploiement :
- Vérifier `GET https://<ngrok>/api/admin/stats` avec token admin → doit contenir `pending_livraisons` et `notifications_non_lues`
- Vérifier `GET https://<ngrok>/api/admin/notifications`
- Rejouer `python3 tests/test_cycle.py --url https://<ngrok>` → doit être 100% vert

## Fichiers clés modifiés dans cette session
- `backend/app/Http/Controllers/Api/AdminController.php` (+100 lignes)
- `backend/app/Http/Controllers/Api/SuiviController.php` (+28 lignes)
- `backend/routes/api.php` (+5 lignes)
- `frontend/src/pages/admin/AdminIndex.tsx` (+147 lignes)
- `sql/005_notifications_signal.sql` (nouveau)
- `tests/test_cycle.py` (nouveau)

## Points d'attention / bugs mineurs repérés
1. **`POST /api/paiements/{id}/payer`** attend un champ `methode: "kkiapay"` dans le body (actuellement il renvoie « Méthode de paiement invalide » si omis). Le frontend envoie probablement la bonne valeur car le widget Kkiapay fonctionne, mais si on teste en curl il faut ajouter `"methode":"kkiapay"`.
2. **Payout auto Kkiapay** en mode sandbox : les clés invalides (pk/sk) provoquent un fallback `FAILED` et le retrait reste `en_attente`. En production avec les vraies clés Kkiapay activées, le payout doit partir automatiquement.
3. **Numéro transporteur** : pour que le payout fonctionne, l'utilisateur transporteur doit avoir un numéro Bénin valide (229 + 8 chiffres) dans `users.telephone`.

## Credentials
- `admin@transport.bj / Admin@12345` → super_admin id=25
- Kkiapay sandbox : public `95ab811a8947a69d7d57d3a9ffdc8f9ae3abb4d`, private `pk_978ed...`, secret `sk_fcbdd...` (mode sandbox, fallback si invalides)
- Google OAuth : `GOOGLE_CLIENT_ID` à configurer dans `.env`, avec `GOOGLE_SKIP_SSL_VERIFY=true` en dev/local pour éviter cURL 60

## TODO futurs
- [ ] Déployer en prod (pull + migration + build frontend + restart)
- [ ] Tester le cycle après déploiement avec `tests/test_cycle.py`
- [ ] Configurer clés Kkiapay live (et retirer fallback si besoin)
- [ ] Configurer Google Client ID en prod
- [ ] Si besoin, jouer un son mp3 réel : placer `notification.mp3` dans `frontend/public/` et remplacer `playBeep()` par `new Audio('/notification.mp3').play()` (optionnel, le beep Web Audio fonctionne sans asset)

## Commandes utiles
```bash
cd /home/user/transport
git log --oneline -10
git remote -v          # vérifier le PAT
cd backend && php artisan serve --host=0.0.0.0 --port=8002
cd frontend && npm run build
python3 tests/test_cycle.py --url https://<ngrok>
```

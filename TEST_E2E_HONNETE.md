# Rapport de test E2E — la vérité brute

**Date :** 2026-09-10
**Serveur testé :** https://e820-137-255-185-221.ngrok-free.app/api (API v2.1, 86 endpoints)
**Script :** `tests/test_honnete.py` (crée 3 comptes réels à chaque exécution et tente un cycle complet colis→voyage→réservation→paiement→livraison→commission→retrait)
**Dernière exécution :** 16/27 OK, 11 FAIL

---

## ✅ Fonctionnalités qui MARCHENT (testées en HTTP réel)

1. Santé de l'API (`GET /api`)
2. Inscription CLIENT (token + utilisateur créés)
3. Inscription TRANSPORTEUR
4. Login ADMIN (`admin@transport.bj` / `Admin@12345`) → token super_admin id=25
5. `GET /auth/me` client & transporteur
6. **Création de colis** par un client (champs : nom_colis, type_produit, nombre_produits, poids, dimensions, pays, ville, date_limite, adresses)
   - Test réel : colis 2.5 kg → retourne `colis_id`, `numero_suivi`, prix_estime=4200 XOF, et un enregistrement paiement en_attente est créé automatiquement.
7. Liste « mes colis » côté client
8. **Proposition de voyage** par un transporteur (numero_permis, vehicule, compagnie, adresse, ville, pays, dates, poids_max, email, téléphone) → `voyage_id` retourné
9. Liste des colis disponibles côté transporteur
10. Consultation du paiement d'un colis
11. Solde/wallet transporteur (reste à 0 tant qu'aucune commission n'est versée — normal)
12. Wallet admin (solde=800, total_genere=180)
13. Liste des retraits admin
14. Dépôt d'un avis 5★ par un client
15. Envoi d'un message contact (sans authentification)

## ❌ Fonctionnalités qui CASSENT — et pourquoi

| # | Endpoint | Erreur | Cause |
|---|---|---|---|
| 8 | `POST /admin/colis/{id}/statut` (approbation) | HTTP 500 « Erreur interne du serveur » | **Code ancien non déployé sur le VPS.** Le code actuellement en ligne plante vraisemblablement sur une colonne manquante ou sur un bug de QueryBuilder déjà corrigé. |
| 10 | `POST /admin/voyages/{id}/statut` | HTTP 500 | Même cause |
| 12 | `POST /reservations` | 404 « Colis introuvable ou non approuvé » | Conséquence directe de #8 — si le colis ne peut pas être approuvé, aucune réservation ne peut être créée. |
| 13 | `POST /reservations/{id}/action` | manque | Conséquence de #12 |
| 15 | Paiement Kkiapay (vérification de transaction) | HTTP 502 « Impossible de vérifier la transaction Kkiapay (réseau ou clés invalides) » | **Clés Kkiapay manquantes ou cURL sortant bloqué** côté serveur. Pas un bug code, une config/réseau. |
| 16 | `POST /suivi` (transporteur marque Livré) | 403 « Non autorisé » | Conséquence — le transporteur n'a pas de réservation acceptée sur ce colis. |
| 17 | `GET /admin/livraisons` | HTTP 500 | Bug `reorder()` corrigé dans le commit `004a940` mais non déployé. |
| 18 | `POST /admin/suivi/{id}/livraison` (confirmation livraison → commission 95/5) | HTTP 500 | Conséquence de #17 + code non déployé. |
| 20 | `POST /retraits` (demande de retrait) | 400 « Solde insuffisant » | **Attendu et correct** : pas de commission versée car la livraison n'a jamais abouti. Le minimum 1000 XOF et le contrôle de solde sont bien appliqués. |
| 21 | Payout Kkiapay admin | manque | Conséquence de #20 (pas de retrait en attente). |
| 25 | `GET /suivi/{numero}` (suivi public) | HTTP 401 « Authentification requise » | **Bug de route** : le endpoint était placé dans le groupe `auth:sanctum`. Corrigé dans le commit `0028694` (poussé) — déplacer en dehors du groupe d'auth. |

## 🔧 Actions obligatoires sur le VPS avant de pouvoir tester le cycle complet

```bash
cd /chemin/vers/transport
sudo -u www-data git pull origin main
cd backend
sudo -u www-data composer install --no-dev -o
php artisan migrate --force   # s'il y a des migrations
php artisan optimize:clear
sudo systemctl reload php8.2-fpm   # adapter à ta version
sudo systemctl reload apache2      # ou nginx
```

Vérifier le `.env` backend :
```env
KKIAPAY_PUBLIC_KEY=...
KKIAPAY_PRIVATE_KEY=...
KKIAPAY_SECRET_KEY=...
KKIAPAY_SANDBOX=true
KKIAPAY_SKIP_SSL_VERIFY=true    # si SSL cURL 60
```

Puis relancer :
```bash
python3 tests/test_honnete.py
```

## 📌 Commits récents à déployer

- `9dbe2a2` — Vrai retrait Kkiapay (flux métier corrigé, page admin Kkiapay)
- `004a940` — Fix 500 sur les listes paginées admin (`reorder()` au lieu de `getQuery()`)
- `9b99a66` — Remplacement des émojis par des icônes FontAwesome (UI)
- `0028694` — **Suivi public rendu accessible sans authentification**

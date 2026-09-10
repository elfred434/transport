# Guide Commission 95% / 5% + Retraits Automatiques

## Problème initial
- Transporteur recevait 5% seulement, admin 95% → inversé.
- Pas de système de retrait.

## Nouvelle répartition (corrigée)
- **Transporteur : 95% du prix_estime** (ex: colis 4000 XOF → 3800 XOF)
- **Admin / Plateforme : 5%** (ex: 200 XOF)
- Config dans `backend/app/Support/Pricing.php` :
  - `COMMISSION_TRANSPORTEUR_RATE = 0.95`
  - `COMMISSION_ADMIN_RATE = 0.05`

## Flux métier complet

1. **Client paie colis** via Kkiapay (widget + verify)
2. **Transporteur accepte réservation** → livre colis → marque `Livré` dans dashboard
3. **Demande livraison** créée dans `suivi_colis` avec `demande_livraison=1`
4. **Admin vérifie auprès du client** (appel / message) puis **confirme** dans `/admin` → `POST /api/admin/suivi/{id}/livraison {decision: confirmer}`
5. **Backend transaction** :
   - `suivi_colis` → `confirme_par_admin=1, demande_livraison=0`
   - `reservations` → `termine`
   - Calcul commissions : 95% transporteur, 5% admin
   - `transporteurs.solde += 95%`
   - `admin_wallet.solde += 5%` (table `admin_wallet` id=1, créée si absente)
   - Crée `retraits` type=transporteur, montant=95%, statut=en_attente, numero=telephone user, reference=AUTO-{colis_id}-{uniqid}, details colis
   - **Payout automatique** via `KkiapayService::payout(telephone, montant, reference)` :
     - Essaie endpoints Kkiapay `/api/v1/payouts`, `/api/v1/transactions/payout`, etc.
     - Headers x-api-key, x-private-key, x-secret-key
     - Si sandbox ou clés invalides → fallback SUCCESS simulé (pour ne pas bloquer)
     - Si SUCCESS → `retraits.statut=paye`, `date_traitement=now()`, `kkiapay_response=json`, et `transporteurs.solde -= montant` (argent envoyé)
     - Si FAILED → `retraits.statut=echec`, solde reste (transporteur pourra demander manuel ou admin retry)
6. **Transporteur** voit dans `/transporteur-stats` :
   - Solde disponible (après payouts auto)
   - Total déjà payé
   - En attente
   - Historique retraits / payouts auto
   - Bouton demande retrait manuel si solde restant (ex: numéro manquant)

## Tables

### admin_wallet
```sql
id INT PK, solde DECIMAL(12,2), created_at, updated_at
```
Single row id=1.

### retraits
```sql
id, user_id FK users, type ENUM('transporteur','admin'), montant, frais, montant_net,
statut ENUM('en_attente','approuve','refuse','paye','echec'),
methode ENUM('mobile_money','virement','kkiapay','autre'),
numero VARCHAR (tel transporteur), operateur, reference UNIQUE,
details TEXT, colis_id FK colis, date_demande, date_traitement, traite_par FK users,
kkiapay_response TEXT
```

## Endpoints

### Transporteur
- `GET /api/transporteur/solde` → {solde, total_paye, en_attente}
- `GET /api/transporteur/retraits` → liste
- `POST /api/transporteur/retraits {montant, numero, methode}` → demande manuelle, décrémente solde, statut en_attente

### Admin
- `GET /api/admin/wallet` → {solde, total_commission_generee, total_paye_transporteurs, total_retraits_admin}
- `GET /api/admin/retraits?type=transporteur&statut=en_attente&search=...` → liste avec join users + colis
- `POST /api/admin/retraits/{id}/decision {decision: approuve|refuse|paye|echec, note}` → approuve/refuse/paye, si refuse rembourse solde
- `POST /api/admin/retraits/{id}/retry` → retente payout Kkiapay auto
- `POST /api/admin/retraits {montant, numero}` → admin retire son propre solde (statut paye direct)

## Frontend

### TransporteurStats.tsx
- Affiche solde 95%, total payé, en attente, répartition
- Formulaire demande retrait manuel
- Table historique retraits (date, montant, statut badge, numéro, ref, colis)

### AdminIndex.tsx
- Sections `wallet` et `retraits` ajoutées
- Stats étendues : admin_wallet_solde, admin_commission_total, total_paye_transporteurs, retraits_en_attente/payes
- Wallet : 4 cartes (solde dispo, commission totale, payé transporteurs, retraits admin) + form retrait admin
- Retraits : filtres type/statut/search, badges, boutons Payer/Approuver/Refuser/Retry payout

## Kkiapay payout

`KkiapayService::payout(phone, amount, reference)` :
- Normalise phone : 22997000000
- Essaie plusieurs URLs, fallback sandbox SUCCESS si 401 ou échec en sandbox
- Retourne array {status: SUCCESS|FAILED, reference, amount, destination, ...}
- En prod, il faudra activer vraies clés Kkiapay et vérifier que compte Kkiapay a solde suffisant pour payouts

## Migration

```bash
mysql transport_db < sql/004_commission_and_retraits.sql
```

Contient :
- CREATE admin_wallet + INSERT id=1
- CREATE retraits

## Test

1. Créer colis 4000 XOF, payer (sandbox)
2. Réserver avec voyage transporteur 66 (tel renseigné)
3. Transporteur marque Livré
4. Admin → colis → Confirmer livraison
   - Doit afficher toast "Livraison confirmée, transporteur 3800 XOF (95%) + admin 200 XOF (5%) - Payout auto SUCCESS"
   - Vérifier `admin_wallet` solde 200, `transporteurs` solde 0 (si payout auto) ou 3800 si échec
   - Vérifier `retraits` ligne paye avec reference AUTO-...
5. Transporteur stats → voir total payé 3800

## Prochaines améliorations
- Ajouter frais Kkiapay (ex: 1% payout)
- Notification SMS au transporteur après payout
- Export CSV retraits
- Dashboard graphique commissions par mois

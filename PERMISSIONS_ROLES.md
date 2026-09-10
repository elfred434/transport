# Matrice des Permissions — Plateforme Transport SPIISTMOVE

> Analyse complète des 63 endpoints API + 26 pages frontend
> Hiérarchie : Client < Transporteur < Admin simple < Super Admin

## Légende
- ✅ Autorisé
- ❌ Interdit
- ⚠️ Partiel / conditionnel
- 🔒 Super Admin uniquement

---

## 1. Authentification & Profil

| Permission | Endpoint / Page | Client | Transporteur | Admin | Super Admin |
|------------|-----------------|--------|--------------|-------|-------------|
| S'inscrire | `POST /api/auth/register` / `/register` | ✅ | ✅ | ✅ | ✅ |
| Se connecter | `POST /api/auth/login` / `/login` | ✅ | ✅ | ✅ | ✅ |
| Demander reset password | `POST /api/auth/reset-request` | ✅ | ✅ | ✅ | ✅ |
| Réinitialiser password | `POST /api/auth/reset-password` | ✅ | ✅ | ✅ | ✅ |
| Se déconnecter | `POST /api/auth/logout` | ✅ | ✅ | ✅ | ✅ |
| Voir son profil courant | `GET /api/auth/me` / `/profil` | ✅ | ✅ | ✅ | ✅ |
| Voir son profil détaillé | `GET /api/profile` | ✅ | ✅ | ✅ | ✅ |
| Modifier son profil | `POST /api/profile` / `/modifier-profil` | ✅ | ✅ | ✅ | ✅ |
| Voir fiche publique transporteur | `GET /api/transporteurs/{id}` / `/profil-transporteur` | ✅ | ✅ | ✅ | ✅ |
| Voir ses stats transporteur | `GET /api/transporteur-stats` / `/transporteur-stats` | ❌ | ✅ (si fiche existe) | ⚠️ (si aussi transporteur) | ⚠️ |

---

## 2. Colis (Cœur métier)

| Permission | Endpoint / Page | Client | Transporteur | Admin | Super Admin |
|------------|-----------------|--------|--------------|-------|-------------|
| Poster un colis | `POST /api/colis` / `/poster-colis` | ✅ | ✅ | ✅ | ✅ |
| Voir mes colis | `GET /api/colis/mine` / `/dashboard` onglet Mes colis | ✅ | ✅ | ✅ | ✅ |
| Voir détail colis (owner) | `GET /api/colis/{id}` / `/colis/:id` | ✅ (si owner) | ✅ (si owner ou approuvé) | ✅ | ✅ |
| Modifier son colis (en_attente) | `POST /api/colis/{id}` | ✅ (owner, en_attente) | ✅ | ❌ (admin ne modifie pas colis d'autrui) | ❌ |
| Voir colis disponibles | `GET /api/colis/available` / `/colis` | ❌ | ✅ (approuvés) | ✅ | ✅ |
| Voir voyages compatibles pour colis | `GET /api/colis/{id}/voyages-compatibles` / `/recherche?colis_id=` | ✅ | ✅ | ✅ | ✅ |
| Voir réservations de son colis | `GET /api/colis/{id}/reservations` / `/reservation-colis` | ✅ (owner) | ❌ | ✅ | ✅ |
| Calcul prix automatique | `Pricing::commission` = `max(1000,1000+1000*poids)*1.2` | Auto | Auto | Auto | Auto |

**Règles :** Prix recalculé à chaque modif, statut par défaut `en_attente`, numéro suivi unique `COLIS...`

---

## 3. Voyages (Transporteurs)

| Permission | Endpoint / Page | Client | Transporteur | Admin | Super Admin |
|------------|-----------------|--------|--------------|-------|-------------|
| Devenir transporteur / proposer voyage | `POST /api/voyages` / `/devenir-transporteur` | ✅ (devient transporteur) | ✅ | ✅ | ✅ |
| Voir mes voyages | `GET /api/voyages/mine` / `/dashboard` onglet Mes voyages | ❌ (message invite à devenir) | ✅ | ⚠️ | ⚠️ |
| Voir voyages disponibles (approuvés, à venir) | `GET /api/voyages/available` / `/recherche` | ✅ | ✅ | ✅ | ✅ |
| Rôle passe à transporteur automatiquement | `users.role` où `client` → `transporteur` | ✅ Auto | — | — | — |
| Création fiche transporteur | `transporteurs` table | Auto à 1er voyage | Auto | Auto | Auto |

---

## 4. Réservations (Mise en relation)

| Permission | Endpoint / Page | Client | Transporteur | Admin | Super Admin |
|------------|-----------------|--------|--------------|-------|-------------|
| Réserver un colis pour son voyage | `POST /api/reservations` | ❌ | ✅ (voyage owner, colis approuvé, compatible) | ❌ | ❌ |
| Voir réservations reçues (sur mes voyages) | `GET /api/reservations/recues` / `/dashboard` onglet Réservations | ❌ | ✅ | ❌ | ❌ |
| Accepter / Refuser réservation (owner colis) | `POST /api/reservations/{id}/action` / `/reservation-colis` | ✅ (owner colis) | ❌ | ❌ | ❌ |
| Action invalide bloquée |  | 400 | 400 | 400 | 400 |

**Compatibilité :** même pays destination, `poids_max >= poids`, `date_depart >= aujourd'hui`

---

## 5. Suivi Livraison

| Permission | Endpoint / Page | Client | Transporteur | Admin | Super Admin |
|------------|-----------------|--------|--------------|-------|-------------|
| Voir suivi par numéro | `GET /api/suivi/{numero}` / `/suivi` | ✅ (public avec numéro) | ✅ | ✅ | ✅ |
| Ajouter étape suivi | `POST /api/suivi` | ❌ (403) | ✅ (si réservation acceptée) | ✅ | ✅ |
| Statuts autorisés | `En attente`, `En cours`, `Livré` | — | ✅ | ✅ | ✅ |
| Marquer Livré → demande confirmation admin | `demande_livraison=1` | — | ✅ | — | — |
| Confirmer / Refuser livraison (admin) | `POST /api/admin/suivi/{id}/livraison` | ❌ | ❌ | ✅ | ✅ |
| Supprimer étape suivi | `DELETE /api/admin/suivi/{id}` | ❌ | ❌ | ✅ | ✅ |

**Commission :** 5% du `prix_estime` crédité à `transporteurs.solde` à la confirmation

---

## 6. Paiements (Simulés)

| Permission | Endpoint / Page | Client | Transporteur | Admin | Super Admin |
|------------|-----------------|--------|--------------|-------|-------------|
| Voir paiement de son colis | `GET /api/paiements/colis/{colis_id}` / `/paiement` | ✅ (owner) | ❌ | ✅ | ✅ |
| Payer (carte_credit / mobile_money) | `POST /api/paiements/{id}/payer` | ✅ | ❌ | ❌ | ❌ |
| Voir mes paiements | `GET /api/paiements/mine` / `/dashboard` onglet Paiements | ✅ | ✅ | ✅ | ✅ |
| Double paiement refusé |  | 400 | 400 | 400 | 400 |
| Données sensibles non stockées (numéro masqué seulement) |  | ✅ | ✅ | ✅ | ✅ |
| Admin change statut paiement | `POST /api/admin/paiements/{id}/statut` | ❌ | ❌ | ✅ | ✅ |

---

## 7. Messagerie User ↔ User

| Permission | Endpoint / Page | Client | Transporteur | Admin | Super Admin |
|------------|-----------------|--------|--------------|-------|-------------|
| Voir conversations | `GET /api/conversations` / `/liste-messagerie` | ✅ | ✅ | ✅ | ✅ |
| Voir messages d'une conversation | `GET /api/messages?destinataire_id=` / `/messagerie` | ✅ | ✅ | ✅ | ✅ |
| Envoyer message à un autre user | `POST /api/messages` | ✅ (pas à soi-même) | ✅ | ✅ | ✅ |
| Envoi vers soi refusé |  | 400 | 400 | 400 | 400 |
| Upload image dans message | `fichier` | ✅ | ✅ | ✅ | ✅ |

---

## 8. Messagerie User ↔ Admin (Support)

| Permission | Endpoint / Page | Client | Transporteur | Admin | Super Admin |
|------------|-----------------|--------|--------------|-------|-------------|
| Envoyer message à l'admin (destinataire imposé = 1er super_admin/admin) | `POST /api/admin-chat` / `/messagerie-admin` | ✅ | ✅ | ❌ (admin choisit destinataire) | ❌ |
| Voir sa conversation avec admin | `GET /api/admin-chat` | ✅ | ✅ | ✅ (avec user_id) | ✅ |
| Admin voit liste conversations support | `GET /api/admin-chat/conversations` / `/admin/messagerie` | ❌ | ❌ | ✅ | ✅ |
| Admin répond à un user | `POST /api/admin-chat` avec `destinataire_id` | ❌ | ❌ | ✅ | ✅ |

---

## 9. Contact (Public + Admin)

| Permission | Endpoint / Page | Client | Transporteur | Admin | Super Admin |
|------------|-----------------|--------|--------------|-------|-------------|
| Envoyer message contact (public, sans auth) | `POST /api/contact` / `/contact` | ✅ | ✅ | ✅ | ✅ |
| Voir ses réponses contact | `GET /api/contact/reponses` / `/reponses` | ✅ | ✅ | ✅ | ✅ |
| Admin voit messages contact (marque lus) | `GET /api/admin/contact-messages` | ❌ | ❌ | ✅ | ✅ |
| Admin répond à message contact | `POST /api/admin/contact-messages/{id}/repondre` | ❌ | ❌ | ✅ | ✅ |
| Admin supprime message contact | `DELETE /api/admin/contact-messages/{id}` | ❌ | ❌ | ✅ | ✅ |

---

## 10. Avis Transporteurs

| Permission | Endpoint / Page | Client | Transporteur | Admin | Super Admin |
|------------|-----------------|--------|--------------|-------|-------------|
| Voir avis d'un transporteur (approuvés) | `GET /api/transporteurs/{id}/avis` | ✅ | ✅ | ✅ | ✅ |
| Déposer avis (1 seul par couple user-transporteur, après livraison) | `POST /api/avis` | ✅ | ✅ | ❌ (pas auto-évaluation) | ❌ |
| Auto-évaluation refusée |  | 400 | 400 | 400 | 400 |
| Note hors 1-5 refusée |  | 400 | 400 | 400 | 400 |
| Double avis refusé |  | 409 | 409 | 409 | 409 |
| Avis non modéré invisible publiquement |  | ✅ (0) | ✅ | ✅ | ✅ |
| Admin modère avis | `POST /api/admin/avis/{id}/statut` / `DELETE /api/admin/avis/{id}` | ❌ | ❌ | ✅ | ✅ |

---

## 11. Administration

### 11.1 Stats

| Permission | Endpoint | Client | Transporteur | Admin | Super Admin |
|------------|----------|--------|--------------|-------|-------------|
| Voir stats globales | `GET /api/admin/stats` / `/admin` onglet stats | ❌ 403 | ❌ 403 | ✅ | ✅ |

Stats : `nb_users`, `nb_clients`, `nb_transporteurs_users`, `nb_admins`, `nb_super_admins`, `nb_colis`, `nb_voyages`, `nb_transporteurs`, `nb_paiements`, `colis_en_attente`, `voyages_en_attente`, `avis_en_attente`, `demandes_livraison`, `montant_total_paye`, `messages_contact_non_lus`

### 11.2 Gestion Utilisateurs

| Permission | Endpoint | Client | Transporteur | Admin simple | Super Admin |
|------------|----------|--------|--------------|--------------|-------------|
| Lister utilisateurs | `GET /api/admin/users` | ❌ | ❌ | ✅ (clients & transporteurs seulement) | ✅ (tous) |
| Filtrer par rôle | `?role=client|transporteur|admin|super_admin` | ❌ | ❌ | ⚠️ (client, transporteur seulement) | ✅ |
| Créer utilisateur | `POST /api/admin/users` | ❌ | ❌ | ✅ (client, transporteur seulement) | ✅ (tous rôles) |
| Changer rôle | `POST /api/admin/users/{id}/role` | ❌ | ❌ | ✅ (client↔transporteur) | ✅ (tous) |
| Supprimer utilisateur | `DELETE /api/admin/users/{id}` | ❌ | ❌ | ✅ (pas admin) | ✅ (pas dernier super_admin) |
| Supprimer son propre compte |  | ❌ bloqué | ❌ bloqué | ❌ bloqué | ❌ bloqué |

### 11.3 Modération Colis/Voyages

| Permission | Endpoint | Client | Transporteur | Admin | Super Admin |
|------------|----------|--------|--------------|-------|-------------|
| Lister colis admin | `GET /api/admin/colis` | ❌ | ❌ | ✅ | ✅ |
| Changer statut colis | `POST /api/admin/colis/{id}/statut` | ❌ | ❌ | ✅ | ✅ |
| Supprimer colis | `DELETE /api/admin/colis/{id}` | ❌ | ❌ | ✅ | ✅ |
| Lister voyages admin | `GET /api/admin/voyages` | ❌ | ❌ | ✅ | ✅ |
| Changer statut voyage | `POST /api/admin/voyages/{id}/statut` | ❌ | ❌ | ✅ | ✅ |
| Supprimer voyage | `DELETE /api/admin/voyages/{id}` | ❌ | ❌ | ✅ | ✅ |
| Lister transporteurs | `GET /api/admin/transporteurs` | ❌ | ❌ | ✅ | ✅ |
| Supprimer fiche transporteur | `DELETE /api/admin/transporteurs/{id}` | ❌ | ❌ | ✅ | ✅ |

### 11.4 Paiements / Avis / Contact (Admin)

| Permission | Endpoint | Admin | Super Admin |
|------------|----------|-------|-------------|
| Lister paiements | `GET /api/admin/paiements` | ✅ | ✅ |
| Changer statut paiement | `POST /api/admin/paiements/{id}/statut` | ✅ | ✅ |
| Lister avis | `GET /api/admin/avis` | ✅ | ✅ |
| Modérer avis | `POST /api/admin/avis/{id}/statut` | ✅ | ✅ |
| Supprimer avis | `DELETE /api/admin/avis/{id}` | ✅ | ✅ |
| Voir messages contact | `GET /api/admin/contact-messages` | ✅ | ✅ |
| Répondre contact | `POST /api/admin/contact-messages/{id}/repondre` | ✅ | ✅ |

---

## 12. Super Administration (🔒 Super Admin uniquement)

| Permission | Endpoint / Page | Admin simple | Super Admin |
|------------|-----------------|--------------|-------------|
| Lister tous les admins | `GET /api/super-admin/admins` / `/super-admin` | ❌ 403 | ✅ |
| Lister rôles disponibles | `GET /api/super-admin/roles` | ❌ 403 | ✅ |
| Promouvoir admin → super_admin | `POST /api/admin/users/{id}/role role=super_admin` | ❌ 403 | ✅ |
| Rétrograder super_admin → admin | `POST /api/admin/users/{id}/role role=admin` | ❌ 403 | ✅ |
| Créer admin / super_admin | `POST /api/admin/users role=admin|super_admin` | ❌ 403 | ✅ |
| Supprimer admin | `DELETE /api/admin/users/{id}` où target=admin | ❌ 403 | ✅ |
| Supprimer dernier super_admin |  | ❌ bloqué | ❌ bloqué (protection) |
| Voir page Super Admin | `/super-admin` | ❌ (page 403) | ✅ |
| Voir badge Super Admin dans sidebar |  | ❌ | ✅ 👑 |

---

## 13. Frontend Pages par Rôle

| Page | Route | Client | Transporteur | Admin | Super Admin |
|------|-------|--------|--------------|-------|-------------|
| Accueil | `/` | ✅ | ✅ | ✅ | ✅ |
| Contact | `/contact` | ✅ | ✅ | ✅ | ✅ |
| Login/Register/Reset | `/login` etc | ✅ | ✅ | ✅ | ✅ |
| Dashboard (tabs colis/voyages/reservations/paiements) | `/dashboard` | ✅ | ✅ | ✅ | ✅ |
| Poster colis | `/poster-colis` | ✅ | ✅ | ✅ | ✅ |
| Colis disponibles | `/colis` | ❌ | ✅ | ✅ | ✅ |
| Détail colis | `/colis/:id` | ✅ | ✅ | ✅ | ✅ |
| Recherche voyages | `/recherche` | ✅ | ✅ | ✅ | ✅ |
| Réservation colis | `/reservation-colis` | ✅ | ✅ | ✅ | ✅ |
| Paiement | `/paiement` | ✅ | ✅ | ✅ | ✅ |
| Suivi | `/suivi` | ✅ | ✅ | ✅ | ✅ |
| Profil / Modifier profil | `/profil` | ✅ | ✅ | ✅ | ✅ |
| Devenir transporteur | `/devenir-transporteur` | ✅ (si pas déjà) | ❌ | ❌ | ❌ |
| Stats transporteur | `/transporteur-stats` | ❌ | ✅ | ⚠️ | ⚠️ |
| Profil transporteur public | `/profil-transporteur` | ✅ | ✅ | ✅ | ✅ |
| Messagerie | `/liste-messagerie`, `/messagerie` | ✅ | ✅ | ✅ | ✅ |
| Messagerie Admin | `/messagerie-admin` | ✅ | ✅ | ✅ | ✅ |
| Réponses contact | `/reponses` | ✅ | ✅ | ✅ | ✅ |
| Admin | `/admin` | ❌ | ❌ | ✅ | ✅ |
| Admin Messagerie | `/admin/messagerie` | ❌ | ❌ | ✅ | ✅ |
| Super Admin | `/super-admin` | ❌ | ❌ | ❌ | ✅ |

---

## 14. Permissions Futures Possibles (à implémenter)

- `can_view_logs` : voir logs système (super_admin)
- `can_manage_settings` : modifier config transport (prix, commission %)
- `can_export_data` : exporter CSV users/colis/paiements
- `can_ban_user` : bannir temporairement au lieu de supprimer
- `can_view_financials` : voir détails financiers complets
- `can_manage_permissions` : éditer matrice permissions (super_admin)
- `can_impersonate` : se connecter en tant que client pour debug (super_admin)
- `can_manage_api_keys` : gérer clés API
- `can_moderate_images` : modérer photos colis/vehicules

---

## 15. Résumé Hiérarchique

```
Client (base)
  └─> Transporteur (Client + voyages + reservations + solde)
        └─> Admin simple (Transporteur + modération colis/voyages/avis/paiements/contact + gestion clients/transporteurs)
              └─> Super Admin (Admin + gestion admins + tous rôles + super-admin routes)
```

**Sécurité :** 
- Admin simple ne peut jamais escalader vers admin/super_admin
- Dernier super_admin protégé contre suppression
- Tokens Sanctum avec TTL 30 jours, hashés en base
- Uploads whitelist JPEG/PNG/GIF/WEBP vérifiés sur contenu réel
- Path traversal bloqué sur /uploads

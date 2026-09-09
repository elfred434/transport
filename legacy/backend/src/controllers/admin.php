<?php
/**
 * Contrôleur Admin : statistiques, gestion des utilisateurs, colis, voyages,
 * transporteurs, paiements, avis et messages de contact.
 * Toutes les routes exigent le rôle admin.
 */

function admin_stats(array $params): void
{
    require_admin();
    $pdo = db();

    $stats = [
        'nb_users' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'nb_colis' => (int) $pdo->query("SELECT COUNT(*) FROM colis")->fetchColumn(),
        'nb_voyages' => (int) $pdo->query("SELECT COUNT(*) FROM voyages")->fetchColumn(),
        'nb_transporteurs' => (int) $pdo->query("SELECT COUNT(*) FROM transporteurs")->fetchColumn(),
        'nb_paiements' => (int) $pdo->query("SELECT COUNT(*) FROM paiements")->fetchColumn(),
        'nb_avis' => (int) $pdo->query("SELECT COUNT(*) FROM avis")->fetchColumn(),
        'colis_en_attente' => (int) $pdo->query("SELECT COUNT(*) FROM colis WHERE statut = 'en_attente'")->fetchColumn(),
        'voyages_en_attente' => (int) $pdo->query("SELECT COUNT(*) FROM voyages WHERE statut = 'en_attente'")->fetchColumn(),
        'avis_en_attente' => (int) $pdo->query("SELECT COUNT(*) FROM avis WHERE statut = 'en_attente'")->fetchColumn(),
        'demandes_livraison' => (int) $pdo->query(
            "SELECT COUNT(*) FROM suivi_colis s
             WHERE s.demande_livraison = 1 AND s.confirme_par_admin = 0
               AND s.statut = 'Livré'
               AND s.date_etape = (SELECT MAX(s2.date_etape) FROM suivi_colis s2 WHERE s2.colis_id = s.colis_id)"
        )->fetchColumn(),
        'montant_total_paye' => (float) $pdo->query("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut = 'paye'")->fetchColumn(),
        'messages_contact_non_lus' => (int) $pdo->query("SELECT COUNT(*) FROM messages_contact WHERE lu_par_admin = 0")->fetchColumn(),
    ];

    json_success($stats);
}

// ---------------------------------------------------------------------------
// Utilisateurs
// ---------------------------------------------------------------------------

function admin_users_list(array $params): void
{
    require_admin();

    $search = query_str('search');
    $role = query_str('role');

    $where = ['1=1'];
    $sqlParams = [];
    if ($search !== '') {
        $where[] = "(nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
        $like = "%$search%";
        array_push($sqlParams, $like, $like, $like);
    }
    if (in_array($role, ['utilisateur', 'transporteur', 'admin'], true)) {
        $where[] = "role = ?";
        $sqlParams[] = $role;
    }

    $stmt = db()->prepare(
        "SELECT id, nom, prenom, email, telephone, role, photo_profil, date_inscription
         FROM users WHERE " . implode(' AND ', $where) . " ORDER BY date_inscription DESC LIMIT 200"
    );
    $stmt->execute($sqlParams);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$u) {
        $u['photo_url'] = file_url($u['photo_profil']);
        unset($u['photo_profil']);
    }
    unset($u);

    json_success($users);
}

function admin_user_create(array $params): void
{
    require_admin();

    $nom = input_str('nom');
    $prenom = input_str('prenom');
    $email = validate_email(input_str('email'));
    $password = validate_password(input_str('password'));
    $role = input_str('role');

    if ($nom === '' || $prenom === '') {
        api_error('Le nom et le prénom sont obligatoires');
    }
    if (!in_array($role, ['utilisateur', 'transporteur', 'admin'], true)) {
        api_error('Rôle invalide');
    }

    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        api_error('Cet email est déjà utilisé', 409);
    }

    db()->prepare(
        "INSERT INTO users (nom, prenom, email, password, role, date_inscription)
         VALUES (?, ?, ?, ?, ?, NOW())"
    )->execute([htmlspecialchars($nom), htmlspecialchars($prenom), $email, password_hash($password, PASSWORD_DEFAULT), $role]);

    json_success(['message' => 'Utilisateur créé', 'id' => (int) db()->lastInsertId()], 201);
}

function admin_user_delete(array $params): void
{
    $admin = require_admin();
    $id = (int) $params['id'];

    if ($id === (int) $admin['id']) {
        api_error('Impossible de supprimer votre propre compte');
    }

    $stmt = db()->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        api_error('Utilisateur introuvable', 404);
    }
    json_success(['message' => 'Utilisateur supprimé']);
}

// ---------------------------------------------------------------------------
// Colis + suivi
// ---------------------------------------------------------------------------

function admin_colis_list(array $params): void
{
    require_admin();

    $statut = query_str('statut');
    $search = query_str('search');

    $where = ['1=1'];
    $sqlParams = [];
    if (in_array($statut, ['en_attente', 'approuve', 'refuse'], true)) {
        $where[] = "c.statut = ?";
        $sqlParams[] = $statut;
    }
    if ($search !== '') {
        $where[] = "(c.nom_colis LIKE ? OR c.numero_suivi LIKE ? OR c.pays LIKE ? OR c.ville LIKE ?)";
        $like = "%$search%";
        array_push($sqlParams, $like, $like, $like, $like);
    }

    $stmt = db()->prepare(
        "SELECT c.*, u.nom, u.prenom, u.email,
            (SELECT s.id FROM suivi_colis s
              WHERE s.colis_id = c.id AND s.demande_livraison = 1 AND s.confirme_par_admin = 0
              ORDER BY s.date_etape DESC LIMIT 1) AS demande_livraison_id,
            (SELECT s.statut FROM suivi_colis s WHERE s.colis_id = c.id ORDER BY s.date_etape DESC LIMIT 1) AS statut_livraison
         FROM colis c
         LEFT JOIN users u ON c.user_id = u.id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY c.date_post DESC
         LIMIT 200"
    );
    $stmt->execute($sqlParams);
    $colis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($colis as &$c) {
        $c = colis_public($c);
    }
    unset($c);

    json_success($colis);
}

function admin_colis_statut(array $params): void
{
    require_admin();
    $id = (int) $params['id'];
    $statut = input_str('statut');

    if (!in_array($statut, ['en_attente', 'approuve', 'refuse'], true)) {
        api_error('Statut invalide');
    }

    $stmt = db()->prepare("UPDATE colis SET statut = ? WHERE id = ?");
    $stmt->execute([$statut, $id]);
    if ($stmt->rowCount() === 0) {
        api_error('Colis introuvable', 404);
    }
    json_success(['message' => "Statut du colis mis à jour ($statut)"]);
}

function admin_colis_delete(array $params): void
{
    require_admin();
    $id = (int) $params['id'];

    $stmt = db()->prepare("DELETE FROM colis WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        api_error('Colis introuvable', 404);
    }
    json_success(['message' => 'Colis supprimé']);
}

/** Confirmation ou refus d'une demande de livraison (étape de suivi). */
function admin_livraison_decision(array $params): void
{
    require_admin();
    $suiviId = (int) $params['id'];
    $decision = input_str('decision'); // 'confirmer' | 'refuser'

    $stmt = db()->prepare("SELECT * FROM suivi_colis WHERE id = ? AND demande_livraison = 1");
    $stmt->execute([$suiviId]);
    $etape = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$etape) {
        api_error('Demande de livraison introuvable', 404);
    }

    if ($decision === 'confirmer') {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE suivi_colis SET confirme_par_admin = 1, demande_livraison = 0 WHERE id = ?")
                ->execute([$suiviId]);

            // Réservations associées → termine
            $pdo->prepare("UPDATE reservations SET statut = 'termine' WHERE colis_id = ? AND statut = 'accepte'")
                ->execute([$etape['colis_id']]);

            // Commission du transporteur : 5 % du prix du colis (règle métier historique)
            $stmt = $pdo->prepare(
                "SELECT c.prix_estime, v.user_id AS transporteur_id
                 FROM colis c
                 JOIN reservations r ON r.colis_id = c.id AND r.statut = 'termine'
                 JOIN voyages v ON v.id = r.voyage_id
                 WHERE c.id = ?
                 LIMIT 1"
            );
            $stmt->execute([$etape['colis_id']]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            $commission = 0.0;
            if ($info) {
                $commission = round((float) $info['prix_estime'] * 0.05, 2);
                $pdo->prepare("UPDATE transporteurs SET solde = solde + ? WHERE user_id = ?")
                    ->execute([$commission, $info['transporteur_id']]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Erreur confirmation livraison : ' . $e->getMessage());
            api_error('Erreur lors de la confirmation de la livraison', 500);
        }
        json_success(['message' => 'Livraison confirmée', 'commission_transporteur' => $commission]);
    } elseif ($decision === 'refuser') {
        db()->prepare("DELETE FROM suivi_colis WHERE id = ?")->execute([$suiviId]);
        json_success(['message' => 'Demande de livraison refusée']);
    }

    api_error('Décision invalide (confirmer|refuser)');
}

function admin_suivi_delete(array $params): void
{
    require_admin();
    $id = (int) $params['id'];

    $stmt = db()->prepare("DELETE FROM suivi_colis WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        api_error('Étape introuvable', 404);
    }
    json_success(['message' => 'Étape de suivi supprimée']);
}

// ---------------------------------------------------------------------------
// Voyages
// ---------------------------------------------------------------------------

function admin_voyages_list(array $params): void
{
    require_admin();

    $statut = query_str('statut');
    $search = query_str('search');

    $where = ['1=1'];
    $sqlParams = [];
    if (in_array($statut, ['en_attente', 'approuve', 'refuse'], true)) {
        $where[] = "v.statut = ?";
        $sqlParams[] = $statut;
    }
    if ($search !== '') {
        $where[] = "(v.pays_depart LIKE ? OR v.pays_destination LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
        $like = "%$search%";
        array_push($sqlParams, $like, $like, $like, $like);
    }

    $stmt = db()->prepare(
        "SELECT v.*, u.nom, u.prenom, u.email,
            (SELECT COUNT(*) FROM reservations r WHERE r.voyage_id = v.id) AS nb_reservations
         FROM voyages v
         LEFT JOIN users u ON v.user_id = u.id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY v.date_post DESC
         LIMIT 200"
    );
    $stmt->execute($sqlParams);
    json_success($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function admin_voyage_statut(array $params): void
{
    require_admin();
    $id = (int) $params['id'];
    $statut = input_str('statut');

    if (!in_array($statut, ['en_attente', 'approuve', 'refuse'], true)) {
        api_error('Statut invalide');
    }

    $stmt = db()->prepare("UPDATE voyages SET statut = ? WHERE id = ?");
    $stmt->execute([$statut, $id]);
    if ($stmt->rowCount() === 0) {
        api_error('Voyage introuvable', 404);
    }
    json_success(['message' => "Statut du voyage mis à jour ($statut)"]);
}

function admin_voyage_delete(array $params): void
{
    require_admin();
    $id = (int) $params['id'];

    $stmt = db()->prepare("DELETE FROM voyages WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        api_error('Voyage introuvable', 404);
    }
    json_success(['message' => 'Voyage supprimé']);
}

// ---------------------------------------------------------------------------
// Transporteurs
// ---------------------------------------------------------------------------

function admin_transporteurs_list(array $params): void
{
    require_admin();

    $search = query_str('search');
    $where = ['1=1'];
    $sqlParams = [];
    if ($search !== '') {
        $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR t.compagnie LIKE ? OR t.ville LIKE ? OR t.pays LIKE ?)";
        $like = "%$search%";
        array_push($sqlParams, $like, $like, $like, $like, $like, $like);
    }

    $stmt = db()->prepare(
        "SELECT t.*, u.nom, u.prenom, u.email, u.telephone,
            (SELECT COUNT(*) FROM reservations r
              JOIN voyages v ON v.id = r.voyage_id
              WHERE v.user_id = t.user_id AND r.statut = 'accepte') AS nb_colis_transportes
         FROM transporteurs t
         JOIN users u ON u.id = t.user_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY t.date_creation DESC
         LIMIT 200"
    );
    $stmt->execute($sqlParams);
    $transporteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($transporteurs as &$t) {
        $t['photo_vehicule_url'] = file_url($t['photo_vehicule'] ?? null);
        unset($t['photo_vehicule']);
    }
    unset($t);

    json_success($transporteurs);
}

function admin_transporteur_delete(array $params): void
{
    require_admin();
    $id = (int) $params['id'];

    $stmt = db()->prepare("DELETE FROM transporteurs WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        api_error('Transporteur introuvable', 404);
    }
    json_success(['message' => 'Fiche transporteur supprimée']);
}

// ---------------------------------------------------------------------------
// Paiements
// ---------------------------------------------------------------------------

function admin_paiements_list(array $params): void
{
    require_admin();

    $statut = query_str('statut');
    $search = query_str('search');

    $where = ['1=1'];
    $sqlParams = [];
    if (in_array($statut, ['en_attente', 'paye', 'echec', 'annule'], true)) {
        $where[] = "p.statut = ?";
        $sqlParams[] = $statut;
    }
    if ($search !== '') {
        $where[] = "(p.reference LIKE ? OR p.numero_transaction LIKE ? OR c.nom_colis LIKE ? OR u.nom LIKE ?)";
        $like = "%$search%";
        array_push($sqlParams, $like, $like, $like, $like);
    }

    $stmt = db()->prepare(
        "SELECT p.*, c.nom_colis, c.numero_suivi, u.nom, u.prenom, u.email
         FROM paiements p
         LEFT JOIN colis c ON p.colis_id = c.id
         LEFT JOIN users u ON p.user_id = u.id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY p.date_creation DESC
         LIMIT 200"
    );
    $stmt->execute($sqlParams);
    json_success($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function admin_paiement_statut(array $params): void
{
    require_admin();
    $id = (int) $params['id'];
    $statut = input_str('statut');

    if (!in_array($statut, ['en_attente', 'paye', 'echec', 'annule'], true)) {
        api_error('Statut invalide');
    }

    $sql = "UPDATE paiements SET statut = ?" . ($statut === 'paye' ? ", date_paiement = NOW()" : "") . " WHERE id = ?";
    $stmt = db()->prepare($sql);
    $stmt->execute([$statut, $id]);
    if ($stmt->rowCount() === 0) {
        api_error('Paiement introuvable', 404);
    }
    json_success(['message' => "Statut du paiement mis à jour ($statut)"]);
}

// ---------------------------------------------------------------------------
// Avis
// ---------------------------------------------------------------------------

function admin_avis_list(array $params): void
{
    require_admin();

    $statut = query_str('statut');
    $where = ['1=1'];
    $sqlParams = [];
    if (in_array($statut, ['en_attente', 'approuve', 'refuse'], true)) {
        $where[] = "a.statut = ?";
        $sqlParams[] = $statut;
    }

    $stmt = db()->prepare(
        "SELECT a.*, u.nom AS user_nom, u.prenom AS user_prenom,
                t.nom AS transporteur_nom, t.prenom AS transporteur_prenom
         FROM avis a
         JOIN users u ON u.id = a.user_id
         JOIN users t ON t.id = a.transporteur_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY a.date_avis DESC
         LIMIT 200"
    );
    $stmt->execute($sqlParams);
    json_success($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function admin_avis_statut(array $params): void
{
    require_admin();
    $id = (int) $params['id'];
    $statut = input_str('statut');

    if (!in_array($statut, ['en_attente', 'approuve', 'refuse'], true)) {
        api_error('Statut invalide');
    }

    $stmt = db()->prepare("UPDATE avis SET statut = ? WHERE id = ?");
    $stmt->execute([$statut, $id]);
    if ($stmt->rowCount() === 0) {
        api_error('Avis introuvable', 404);
    }
    json_success(['message' => "Statut de l'avis mis à jour ($statut)"]);
}

function admin_avis_delete(array $params): void
{
    require_admin();
    $id = (int) $params['id'];

    $stmt = db()->prepare("DELETE FROM avis WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        api_error('Avis introuvable', 404);
    }
    json_success(['message' => 'Avis supprimé']);
}

// ---------------------------------------------------------------------------
// Messages de contact
// ---------------------------------------------------------------------------

function admin_contact_list(array $params): void
{
    require_admin();

    $repondu = query_str('repondu');
    $where = ['1=1'];
    $sqlParams = [];
    if ($repondu === 'oui') { $where[] = "reponse IS NOT NULL"; }
    if ($repondu === 'non') { $where[] = "reponse IS NULL"; }

    $stmt = db()->prepare(
        "SELECT * FROM messages_contact WHERE " . implode(' AND ', $where) . "
         ORDER BY date_envoi DESC LIMIT 200"
    );
    $stmt->execute($sqlParams);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db()->query("UPDATE messages_contact SET lu_par_admin = 1 WHERE lu_par_admin = 0");

    json_success($messages);
}

function admin_contact_repondre(array $params): void
{
    require_admin();
    $id = (int) $params['id'];
    $reponse = input_str('reponse');

    if ($reponse === '' || mb_strlen($reponse) > 5000) {
        api_error('Réponse invalide');
    }

    $stmt = db()->prepare("UPDATE messages_contact SET reponse = ?, date_reponse = NOW(), lu_par_utilisateur = 0 WHERE id = ?");
    $stmt->execute([$reponse, $id]);
    if ($stmt->rowCount() === 0) {
        api_error('Message introuvable', 404);
    }
    json_success(['message' => 'Réponse envoyée']);
}

function admin_contact_delete(array $params): void
{
    require_admin();
    $id = (int) $params['id'];

    $stmt = db()->prepare("DELETE FROM messages_contact WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        api_error('Message introuvable', 404);
    }
    json_success(['message' => 'Message supprimé']);
}

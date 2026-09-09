<?php
/**
 * Contrôleur Voyages & Réservations : proposer un voyage (devenir transporteur),
 * voyages disponibles, réservations (création, réception, acceptation/refus).
 */

function voyage_create(array $params): void
{
    $user = require_auth();
    $uid = (int) $user['id'];

    $numero_permis    = input_str('numero_permis');
    $vehicule         = input_str('vehicule');
    $compagnie        = input_str('compagnie');
    $adresse          = input_str('adresse');
    $ville            = input_str('ville');
    $pays             = input_str('pays');
    $pays_depart      = input_str('pays_depart');
    $pays_destination = input_str('pays_destination');
    $date_depart      = input_str('date_depart');
    $heure_depart     = input_str('heure_depart');
    $poids_max        = input_float('poids_max');
    $email            = input_str('email');
    $telephone        = input_str('telephone');

    $erreurs = [];
    if ($numero_permis === '')                        $erreurs[] = 'numéro de permis';
    if ($vehicule === '')                             $erreurs[] = 'véhicule';
    if ($pays_depart === '')                          $erreurs[] = 'pays de départ';
    if ($pays_destination === '')                     $erreurs[] = 'pays de destination';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_depart)
        || strtotime($date_depart) < strtotime('today')) $erreurs[] = 'date de départ';
    if (!preg_match('/^\d{2}:\d{2}$/', $heure_depart)) $erreurs[] = 'heure de départ';
    if ($poids_max <= 0 || $poids_max > 100000)        $erreurs[] = 'poids maximum';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $erreurs[] = 'email';
    if ($erreurs) {
        api_error('Champs invalides : ' . implode(', ', $erreurs));
    }

    $photo = handle_image_upload($_FILES['photo_vehicule'] ?? [], 'vehicules', 'vehicule');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO voyages (user_id, pays_depart, pays_destination, date_depart, heure_depart, poids_max, email, telephone)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$uid, $pays_depart, $pays_destination, $date_depart, $heure_depart, $poids_max, $email, $telephone]);
        $voyageId = (int) $pdo->lastInsertId();

        // Passer transporteur (sans jamais écraser le rôle admin)
        $pdo->prepare("UPDATE users SET role = 'transporteur' WHERE id = ? AND role = 'utilisateur'")
            ->execute([$uid]);

        $stmt = $pdo->prepare("SELECT id FROM transporteurs WHERE user_id = ?");
        $stmt->execute([$uid]);
        if (!$stmt->fetch()) {
            $pdo->prepare(
                "INSERT INTO transporteurs
                    (user_id, numero_permis, vehicule, compagnie, adresse, ville, pays, photo_vehicule, date_creation)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            )->execute([$uid, $numero_permis, $vehicule, $compagnie, $adresse, $ville, $pays, $photo]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Erreur voyage_create : ' . $e->getMessage());
        api_error('Une erreur est survenue lors de la proposition de voyage', 500);
    }

    json_success([
        'message' => 'Votre voyage a été proposé. Il sera visible après validation par un administrateur.',
        'voyage_id' => $voyageId,
    ], 201);
}

function voyages_available(array $params): void
{
    require_auth();

    $search = query_str('search');
    $pays_depart = query_str('pays_depart');
    $pays_destination = query_str('pays_destination');

    $where = ["v.statut = 'approuve'", "v.date_depart >= CURDATE()"];
    $sqlParams = [];
    if ($search !== '') {
        $where[] = "(v.pays_depart LIKE ? OR v.pays_destination LIKE ?)";
        $sqlParams[] = "%$search%";
        $sqlParams[] = "%$search%";
    }
    if ($pays_depart !== '')      { $where[] = "v.pays_depart = ?";      $sqlParams[] = $pays_depart; }
    if ($pays_destination !== '') { $where[] = "v.pays_destination = ?"; $sqlParams[] = $pays_destination; }

    $stmt = db()->prepare(
        "SELECT v.*, u.nom, u.prenom, u.id AS transporteur_id, u.photo_profil
         FROM voyages v
         LEFT JOIN users u ON v.user_id = u.id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY v.date_depart ASC
         LIMIT 100"
    );
    $stmt->execute($sqlParams);
    $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($voyages as &$v) {
        $v['photo_url'] = file_url($v['photo_profil']);
        unset($v['photo_profil'], $v['email'], $v['telephone']);
    }
    unset($v);

    json_success($voyages);
}

function voyages_mine(array $params): void
{
    $user = require_auth();

    $stmt = db()->prepare(
        "SELECT v.*,
            (SELECT COUNT(*) FROM reservations r WHERE r.voyage_id = v.id) AS nb_reservations
         FROM voyages v
         WHERE v.user_id = ?
         ORDER BY v.date_post DESC"
    );
    $stmt->execute([(int) $user['id']]);
    json_success($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/** Un transporteur réserve un colis approuvé pour l'un de SES voyages. */
function reservation_create(array $params): void
{
    $user = require_auth();
    $uid = (int) $user['id'];

    $colisId  = input_int('colis_id');
    $voyageId = input_int('voyage_id');
    if ($colisId <= 0 || $voyageId <= 0) {
        api_error('colis_id et voyage_id sont obligatoires');
    }

    $stmt = db()->prepare("SELECT id FROM voyages WHERE id = ? AND user_id = ?");
    $stmt->execute([$voyageId, $uid]);
    if (!$stmt->fetch()) {
        api_error('Ce voyage ne vous appartient pas', 403);
    }

    $stmt = db()->prepare("SELECT id FROM colis WHERE id = ? AND statut = 'approuve'");
    $stmt->execute([$colisId]);
    if (!$stmt->fetch()) {
        api_error('Colis introuvable ou non approuvé', 404);
    }

    $stmt = db()->prepare("SELECT id FROM reservations WHERE colis_id = ? AND voyage_id = ?");
    $stmt->execute([$colisId, $voyageId]);
    if ($stmt->fetch()) {
        api_error('Vous avez déjà réservé ce colis pour ce voyage', 409);
    }

    db()->prepare("INSERT INTO reservations (colis_id, voyage_id, statut) VALUES (?, ?, 'en_attente')")
        ->execute([$colisId, $voyageId]);

    json_success(['message' => 'Votre réservation a été envoyée au propriétaire du colis'], 201);
}

/** Réservations sur un colis (propriétaire du colis ou admin). */
function reservations_for_colis(array $params): void
{
    $user = require_auth();
    $colisId = (int) $params['id'];

    $stmt = db()->prepare("SELECT user_id FROM colis WHERE id = ?");
    $stmt->execute([$colisId]);
    $colis = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$colis) {
        api_error('Colis introuvable', 404);
    }
    if ((int) $colis['user_id'] !== (int) $user['id'] && $user['role'] !== 'admin') {
        api_error('Accès refusé', 403);
    }

    $stmt = db()->prepare(
        "SELECT r.*, v.pays_depart, v.pays_destination, v.date_depart, v.heure_depart,
                u.id AS transporteur_id, u.nom, u.prenom, u.email, u.telephone
         FROM reservations r
         JOIN voyages v ON r.voyage_id = v.id
         JOIN users u ON v.user_id = u.id
         WHERE r.colis_id = ?
         ORDER BY r.date_reservation DESC"
    );
    $stmt->execute([$colisId]);
    json_success($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/** Réservations reçues par le transporteur connecté (sur ses voyages). */
function reservations_recues(array $params): void
{
    $user = require_auth();

    $stmt = db()->prepare(
        "SELECT r.*, c.nom_colis, c.pays, c.ville, c.poids, c.image_colis, c.numero_suivi,
                c.prix_estime, c.user_id AS client_id,
                v.pays_depart, v.pays_destination, v.date_depart, v.heure_depart,
                cu.nom AS client_nom, cu.prenom AS client_prenom, cu.telephone AS client_tel
         FROM reservations r
         JOIN voyages v ON v.id = r.voyage_id
         JOIN colis c ON c.id = r.colis_id
         LEFT JOIN users cu ON cu.id = c.user_id
         WHERE v.user_id = ?
         ORDER BY r.date_reservation DESC"
    );
    $stmt->execute([(int) $user['id']]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reservations as &$r) {
        $s = db()->prepare("SELECT statut FROM suivi_colis WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1");
        $s->execute([$r['colis_id']]);
        $r['statut_suivi'] = $s->fetchColumn() ?: 'En attente';
        $r['image_url'] = file_url($r['image_colis']);
        unset($r['image_colis']);
    }
    unset($r);

    json_success($reservations);
}

/** Le propriétaire du colis accepte / refuse / annule une réservation. */
function reservation_action(array $params): void
{
    $user = require_auth();
    $reservationId = (int) $params['id'];
    $action = input_str('action');

    $actions_valides = ['accepte', 'refuse', 'annule'];
    if (!in_array($action, $actions_valides, true)) {
        api_error('Action invalide');
    }

    $stmt = db()->prepare(
        "SELECT r.*, c.user_id AS colis_owner FROM reservations r
         JOIN colis c ON c.id = r.colis_id
         WHERE r.id = ?"
    );
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reservation) {
        api_error('Réservation introuvable', 404);
    }
    if ((int) $reservation['colis_owner'] !== (int) $user['id'] && $user['role'] !== 'admin') {
        api_error('Vous n\'êtes pas autorisé à modifier cette réservation', 403);
    }

    $nouveau_statut = ($action === 'annule') ? 'en_attente' : $action;
    db()->prepare("UPDATE reservations SET statut = ? WHERE id = ?")
        ->execute([$nouveau_statut, $reservationId]);

    if ($action === 'annule') {
        db()->prepare("DELETE FROM suivi_colis WHERE colis_id = ?")
            ->execute([$reservation['colis_id']]);
    }

    json_success(['message' => 'Réservation mise à jour', 'statut' => $nouveau_statut]);
}

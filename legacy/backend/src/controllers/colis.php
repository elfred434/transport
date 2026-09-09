<?php
/**
 * Contrôleur Colis : liste des colis de l'utilisateur, colis disponibles
 * (transporteurs), détail, création (avec paiement), modification,
 * voyages compatibles.
 */

const COLIS_TYPES_VALIDES = ['alimentaire', 'electronique', 'vetements', 'documents', 'autre'];

function colis_mine(array $params): void
{
    $user = require_auth();

    $stmt = db()->prepare(
        "SELECT c.*,
            (SELECT COUNT(*) FROM reservations r WHERE r.colis_id = c.id AND r.statut = 'accepte') AS nb_reservations
         FROM colis c
         WHERE c.user_id = ?
         ORDER BY c.date_post DESC"
    );
    $stmt->execute([(int) $user['id']]);
    $colis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($colis as &$c) {
        // Dernière étape de suivi
        $s = db()->prepare("SELECT statut, confirme_par_admin, demande_livraison FROM suivi_colis WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1");
        $s->execute([$c['id']]);
        $last = $s->fetch(PDO::FETCH_ASSOC);
        $c['statut_livraison'] = $last['statut'] ?? null;
        $c['suivi_confirme_par_admin'] = (bool) ($last['confirme_par_admin'] ?? false);
        $c['demande_livraison'] = (bool) ($last['demande_livraison'] ?? false);

        // Réservations acceptées (transporteurs affectés)
        $c['reservations'] = [];
        if ((int) $c['nb_reservations'] > 0) {
            $r = db()->prepare(
                "SELECT r.id, r.voyage_id, u.id AS transporteur_id, u.nom, u.prenom, u.telephone, u.email,
                        u.photo_profil
                 FROM reservations r
                 JOIN voyages v ON r.voyage_id = v.id
                 JOIN users u ON v.user_id = u.id
                 WHERE r.colis_id = ? AND r.statut = 'accepte'"
            );
            $r->execute([$c['id']]);
            $reservations = $r->fetchAll(PDO::FETCH_ASSOC);
            foreach ($reservations as &$res) {
                $res['photo_url'] = file_url($res['photo_profil']);
                unset($res['photo_profil']);
                // Note moyenne du transporteur
                $a = db()->prepare("SELECT AVG(note) AS note_moyenne, COUNT(*) AS nb_avis FROM avis WHERE transporteur_id = ? AND statut = 'approuve'");
                $a->execute([$res['transporteur_id']]);
                $st = $a->fetch(PDO::FETCH_ASSOC);
                $res['note_moyenne'] = $st['note_moyenne'] !== null ? round((float) $st['note_moyenne'], 2) : null;
                $res['nb_avis'] = (int) $st['nb_avis'];
            }
            unset($res);
            $c['reservations'] = $reservations;
        }

        $c = colis_public($c);
    }
    unset($c);

    json_success($colis);
}

/** Colis approuvés visibles par les transporteurs (recherche optionnelle). */
function colis_available(array $params): void
{
    require_auth();

    $search = query_str('search');
    $pays   = query_str('pays');
    $ville  = query_str('ville');
    $type   = query_str('type');

    $where = ["c.statut = 'approuve'"];
    $params_sql = [];
    if ($search !== '') {
        $where[] = "(c.nom_colis LIKE ? OR c.type_produit LIKE ? OR c.pays LIKE ? OR c.ville LIKE ?)";
        $like = "%$search%";
        array_push($params_sql, $like, $like, $like, $like);
    }
    if ($pays !== '')  { $where[] = "c.pays = ?";  $params_sql[] = $pays; }
    if ($ville !== '') { $where[] = "c.ville = ?"; $params_sql[] = $ville; }
    if ($type !== '' && in_array($type, COLIS_TYPES_VALIDES, true)) { $where[] = "c.type_produit = ?"; $params_sql[] = $type; }

    $sql = "SELECT c.*, u.nom, u.prenom
            FROM colis c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.date_post DESC
            LIMIT 100";
    $stmt = db()->prepare($sql);
    $stmt->execute($params_sql);
    $colis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($colis as &$c) {
        // Masquer l'email/téléphone du propriétaire tant qu'aucune réservation n'est acceptée
        $c['proprietaire'] = $c['prenom'] . ' ' . $c['nom'];
        unset($c['nom'], $c['prenom']);
        $c = colis_public($c);
    }
    unset($c);

    json_success($colis);
}

function colis_show(array $params): void
{
    $user = require_auth();
    $id = (int) $params['id'];

    $stmt = db()->prepare(
        "SELECT c.*, u.nom, u.prenom, u.email AS owner_email, u.telephone AS owner_tel, u.photo_profil AS owner_photo
         FROM colis c LEFT JOIN users u ON c.user_id = u.id
         WHERE c.id = ?"
    );
    $stmt->execute([$id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
        api_error('Colis introuvable', 404);
    }

    $isOwner = (int) $c['user_id'] === (int) $user['id'];
    $isAdmin = $user['role'] === 'admin';
    if (!$isOwner && !$isAdmin && $c['statut'] !== 'approuve') {
        api_error('Accès refusé', 403);
    }

    $c['owner_photo_url'] = file_url($c['owner_photo']);
    unset($c['owner_photo']);
    if (!$isOwner && !$isAdmin) {
        unset($c['owner_email'], $c['owner_tel']);
    }

    // Étapes de suivi visibles
    $s = db()->prepare(
        "SELECT statut, date_etape FROM suivi_colis
         WHERE colis_id = ? AND (confirme_par_admin = TRUE OR statut != 'Livré')
         ORDER BY date_etape ASC"
    );
    $s->execute([$id]);
    $c['etapes_suivi'] = $s->fetchAll(PDO::FETCH_ASSOC);

    json_success(colis_public($c));
}

function colis_create(array $params): void
{
    $user = require_auth();
    $uid = (int) $user['id'];

    $nom_colis     = input_str('nom_colis');
    $type_produit  = input_str('type_produit');
    $nombre        = input_int('nombre_produits');
    $poids         = input_float('poids');
    $dimensions    = input_str('dimensions');
    $pays          = input_str('pays');
    $ville         = input_str('ville');
    $date_limite   = input_str('date_limite');
    $adresse_depart      = input_str('adresse_depart');
    $adresse_destination = input_str('adresse_destination');

    $erreurs = [];
    if ($nom_colis === '' || mb_strlen($nom_colis) > 100) $erreurs[] = 'nom du colis';
    if (!in_array($type_produit, COLIS_TYPES_VALIDES, true)) $erreurs[] = 'type de produit';
    if ($nombre < 1 || $nombre > 10000) $erreurs[] = 'nombre de produits';
    if ($poids <= 0 || $poids > 1000) $erreurs[] = 'poids';
    if ($pays === '' || $ville === '') $erreurs[] = 'destination';
    if ($adresse_depart === '' || $adresse_destination === '') $erreurs[] = 'adresses';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_limite) || strtotime($date_limite) < strtotime('today')) {
        $erreurs[] = 'date limite';
    }
    if ($erreurs) {
        api_error('Champs invalides : ' . implode(', ', $erreurs));
    }

    $image = handle_image_upload($_FILES['image_colis'] ?? [], 'colis', 'colis');
    $prix_final = calc_prix($poids);

    do {
        $numero_suivi = 'COLIS' . strtoupper(bin2hex(random_bytes(5)));
        $stmt = db()->prepare("SELECT id FROM colis WHERE numero_suivi = ?");
        $stmt->execute([$numero_suivi]);
    } while ($stmt->fetch());

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO colis (user_id, nom_colis, image_colis, type_produit, nombre_produits, poids,
                                dimensions, pays, ville, date_limite, adresse_depart, adresse_destination,
                                prix_estime, numero_suivi, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')"
        );
        $stmt->execute([
            $uid, $nom_colis, $image, $type_produit, $nombre, $poids,
            $dimensions, $pays, $ville, $date_limite, $adresse_depart, $adresse_destination,
            $prix_final, $numero_suivi,
        ]);
        $colisId = (int) $pdo->lastInsertId();

        $reference = 'PAY' . strtoupper(bin2hex(random_bytes(6)));
        $stmt = $pdo->prepare(
            "INSERT INTO paiements (user_id, colis_id, montant, reference, statut, date_creation)
             VALUES (?, ?, ?, ?, 'en_attente', NOW())"
        );
        $stmt->execute([$uid, $colisId, $prix_final, $reference]);
        $paiementId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Erreur création colis : ' . $e->getMessage());
        api_error('Une erreur est survenue lors de la création du colis', 500);
    }

    json_success([
        'colis_id' => $colisId,
        'numero_suivi' => $numero_suivi,
        'paiement' => ['id' => $paiementId, 'reference' => $reference, 'montant' => $prix_final],
    ], 201);
}

function colis_update(array $params): void
{
    $user = require_auth();
    $id = (int) $params['id'];

    $stmt = db()->prepare("SELECT * FROM colis WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, (int) $user['id']]);
    $colis = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$colis) {
        api_error('Colis introuvable ou modification non autorisée', 404);
    }

    $nom_colis    = input_str('nom_colis', $colis['nom_colis']);
    $type_produit = input_str('type_produit', $colis['type_produit']);
    $nombre       = input_int('nombre_produits', (int) $colis['nombre_produits']);
    $poids        = input_float('poids', (float) $colis['poids']);
    $dimensions   = input_str('dimensions', (string) $colis['dimensions']);
    $pays         = input_str('pays', (string) $colis['pays']);
    $ville        = input_str('ville', (string) $colis['ville']);
    $date_limite  = input_str('date_limite', (string) $colis['date_limite']);
    $adresse_depart      = input_str('adresse_depart', (string) $colis['adresse_depart']);
    $adresse_destination = input_str('adresse_destination', (string) $colis['adresse_destination']);

    if ($nom_colis === '' || !in_array($type_produit, COLIS_TYPES_VALIDES, true)
        || $nombre < 1 || $poids <= 0 || $poids > 1000
        || $pays === '' || $ville === ''
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_limite)) {
        api_error('Données invalides');
    }

    $image = $colis['image_colis'];
    $nouvelle = handle_image_upload($_FILES['image_colis'] ?? [], 'colis', 'colis');
    if ($nouvelle) {
        $image = $nouvelle;
        if ($colis['image_colis'] && str_starts_with($colis['image_colis'], 'uploads/')) {
            $old = realpath(STORAGE_PATH . '/' . $colis['image_colis']);
            if ($old && is_file($old)) {
                unlink($old);
            }
        }
    }

    // Prix recalculé côté serveur
    $prix = calc_prix($poids);

    db()->prepare(
        "UPDATE colis SET nom_colis = ?, image_colis = ?, type_produit = ?, nombre_produits = ?,
                poids = ?, dimensions = ?, pays = ?, ville = ?, date_limite = ?,
                adresse_depart = ?, adresse_destination = ?, prix_estime = ?
         WHERE id = ?"
    )->execute([
        $nom_colis, $image, $type_produit, $nombre, $poids, $dimensions,
        $pays, $ville, $date_limite, $adresse_depart, $adresse_destination, $prix, $id,
    ]);

    // Répercuter le nouveau prix sur un paiement encore en attente
    db()->prepare("UPDATE paiements SET montant = ? WHERE colis_id = ? AND statut = 'en_attente'")
        ->execute([$prix, $id]);

    json_success(['message' => 'Colis mis à jour', 'prix_estime' => $prix]);
}

/** Voyages compatibles avec un colis (même logique que l'ancien resultat.php). */
function colis_voyages_compatibles(array $params): void
{
    $user = require_auth();
    $colisId = (int) $params['id'];

    $stmt = db()->prepare("SELECT * FROM colis WHERE id = ?");
    $stmt->execute([$colisId]);
    $colis = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$colis) {
        api_error('Colis introuvable', 404);
    }
    if ((int) $colis['user_id'] !== (int) $user['id'] && $user['role'] !== 'admin') {
        api_error('Accès refusé', 403);
    }

    $stmt = db()->prepare(
        "SELECT v.*, u.nom, u.prenom, u.photo_profil, u.id AS transporteur_id
         FROM voyages v
         LEFT JOIN users u ON v.user_id = u.id
         WHERE v.pays_destination = ?
           AND v.poids_max >= ?
           AND v.date_depart >= CURDATE()
           AND v.statut = 'approuve'
         ORDER BY v.date_depart ASC"
    );
    $stmt->execute([$colis['pays'], $colis['poids']]);
    $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($voyages as &$v) {
        $v['photo_url'] = file_url($v['photo_profil']);
        unset($v['photo_profil']);
    }
    unset($v);

    json_success(['colis' => colis_public($colis), 'voyages' => $voyages]);
}

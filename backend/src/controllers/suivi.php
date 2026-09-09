<?php
/**
 * Contrôleur Suivi : consultation du suivi par numéro, ajout d'étapes
 * (admin ou transporteur affecté).
 */

const SUIVI_STATUTS_VALIDES = ['En attente', 'En cours', 'Livré'];

function suivi_show(array $params): void
{
    require_auth();
    $numero = $params['numero'];

    $stmt = db()->prepare(
        "SELECT c.*, u.nom, u.prenom FROM colis c
         LEFT JOIN users u ON c.user_id = u.id
         WHERE c.numero_suivi = ?"
    );
    $stmt->execute([$numero]);
    $colis = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$colis) {
        api_error('Colis introuvable pour ce numéro de suivi', 404);
    }

    $stmt = db()->prepare(
        "SELECT statut, date_etape FROM suivi_colis
         WHERE colis_id = ? AND (confirme_par_admin = TRUE OR statut != 'Livré')
         ORDER BY date_etape ASC"
    );
    $stmt->execute([$colis['id']]);
    $etapes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = db()->prepare("SELECT statut FROM suivi_colis WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1");
    $stmt->execute([$colis['id']]);
    $dernier = $stmt->fetchColumn() ?: 'En attente';

    json_success([
        'colis' => colis_public($colis),
        'etapes' => $etapes,
        'statut_actuel' => $dernier,
    ]);
}

function suivi_add(array $params): void
{
    $user = require_auth();

    $colisId = input_int('colis_id');
    $numero  = input_str('numero_suivi');
    $statut  = input_str('statut');

    if (!in_array($statut, SUIVI_STATUTS_VALIDES, true)) {
        api_error('Statut invalide');
    }

    if ($colisId > 0) {
        $stmt = db()->prepare("SELECT id FROM colis WHERE id = ?");
        $stmt->execute([$colisId]);
    } else {
        $stmt = db()->prepare("SELECT id FROM colis WHERE numero_suivi = ?");
        $stmt->execute([$numero]);
    }
    $colis = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$colis) {
        api_error('Colis introuvable', 404);
    }
    $colisId = (int) $colis['id'];

    $isAdmin = $user['role'] === 'admin';
    if (!$isAdmin && !is_transporteur_of_colis($colisId, (int) $user['id'])) {
        api_error('Vous n\'êtes pas autorisé à modifier le suivi de ce colis', 403);
    }

    // Colis déjà livré et confirmé ?
    $stmt = db()->prepare(
        "SELECT statut, confirme_par_admin FROM suivi_colis
         WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1"
    );
    $stmt->execute([$colisId]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($last && $last['statut'] === 'Livré' && $last['confirme_par_admin']) {
        api_error('Impossible de modifier le statut d\'un colis déjà livré et confirmé');
    }

    if ($statut === 'Livré' && !$isAdmin) {
        // Demande de confirmation envoyée à l'admin
        db()->prepare("INSERT INTO suivi_colis (colis_id, statut, demande_livraison) VALUES (?, ?, TRUE)")
            ->execute([$colisId, $statut]);
        json_success(['message' => 'Demande de confirmation de livraison envoyée à l\'administrateur'], 201);
    }

    db()->prepare("INSERT INTO suivi_colis (colis_id, statut) VALUES (?, ?)")
        ->execute([$colisId, $statut]);
    json_success(['message' => 'Étape de suivi ajoutée'], 201);
}

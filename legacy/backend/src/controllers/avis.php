<?php
/**
 * Contrôleur Avis : consulter les avis d'un transporteur, déposer un avis.
 */

function avis_for_transporteur(array $params): void
{
    require_auth();
    $transporteurId = (int) $params['id'];

    $stmt = db()->prepare(
        "SELECT a.*, u.nom, u.prenom, u.photo_profil
         FROM avis a
         JOIN users u ON a.user_id = u.id
         WHERE a.transporteur_id = ? AND a.statut = 'approuve'
         ORDER BY a.date_avis DESC"
    );
    $stmt->execute([$transporteurId]);
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($avis as &$a) {
        $a['photo_url'] = file_url($a['photo_profil']);
        unset($a['photo_profil']);
    }
    unset($a);

    json_success($avis);
}

function avis_create(array $params): void
{
    $user = require_auth();
    $uid = (int) $user['id'];

    $transporteurId = input_int('transporteur_id');
    $note = input_int('note');
    $commentaire = input_str('commentaire');
    $colisId = input_int('colis_id') ?: null;

    if ($transporteurId <= 0 || $transporteurId === $uid) {
        api_error('Transporteur invalide');
    }
    if ($note < 1 || $note > 5) {
        api_error('La note doit être entre 1 et 5 étoiles');
    }
    if ($commentaire === '' || mb_strlen($commentaire) > 2000) {
        api_error('Le commentaire est obligatoire (2000 caractères max)');
    }

    // Le transporteur doit exister
    $stmt = db()->prepare("SELECT id FROM transporteurs WHERE user_id = ?");
    $stmt->execute([$transporteurId]);
    if (!$stmt->fetch()) {
        api_error('Cet utilisateur n\'est pas transporteur', 404);
    }

    // Un seul avis par utilisateur et par transporteur
    $stmt = db()->prepare("SELECT id FROM avis WHERE user_id = ? AND transporteur_id = ?");
    $stmt->execute([$uid, $transporteurId]);
    if ($stmt->fetch()) {
        api_error('Vous avez déjà posté un avis pour ce transporteur', 409);
    }

    db()->prepare(
        "INSERT INTO avis (transporteur_id, user_id, colis_id, note, commentaire, date_avis, statut)
         VALUES (?, ?, ?, ?, ?, NOW(), 'en_attente')"
    )->execute([$transporteurId, $uid, $colisId, $note, $commentaire]);

    json_success(['message' => 'Votre avis a été soumis et sera publié après modération'], 201);
}

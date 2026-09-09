<?php
/**
 * Contrôleur Users : profil personnel, fiche publique transporteur,
 * statistiques transporteur.
 */

function profile_show(array $params): void
{
    $user = require_auth();

    $stmt = db()->prepare("SELECT * FROM transporteurs WHERE user_id = ?");
    $stmt->execute([(int) $user['id']]);
    $transporteur = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($transporteur) {
        $transporteur['photo_vehicule_url'] = file_url($transporteur['photo_vehicule'] ?? null);
        unset($transporteur['photo_vehicule']);
    }

    json_success([
        'user' => user_public($user),
        'transporteur' => $transporteur ?: null,
    ]);
}

function profile_update(array $params): void
{
    $user = require_auth();

    $nom       = input_str('nom');
    $prenom    = input_str('prenom');
    $email     = validate_email(input_str('email'));
    $telephone = input_str('telephone');

    if ($nom === '' || $prenom === '') {
        api_error('Le nom et le prénom sont obligatoires');
    }

    $stmt = db()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, (int) $user['id']]);
    if ($stmt->fetch()) {
        api_error('Cet email est déjà utilisé par un autre compte', 409);
    }

    $photo = handle_image_upload($_FILES['photo_profil'] ?? [], '', 'profil_' . $user['id']);

    $sql = "UPDATE users SET nom = ?, prenom = ?, email = ?, telephone = ?";
    $values = [htmlspecialchars($nom), htmlspecialchars($prenom), $email, htmlspecialchars($telephone)];
    if ($photo) {
        $sql .= ", photo_profil = ?";
        $values[] = $photo;
    }
    $sql .= " WHERE id = ?";
    $values[] = (int) $user['id'];
    db()->prepare($sql)->execute($values);

    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int) $user['id']]);
    json_success(['user' => user_public($stmt->fetch(PDO::FETCH_ASSOC))]);
}

/** Fiche publique d'un transporteur (pour l'id : users.id). */
function transporteur_show(array $params): void
{
    require_auth();
    $id = (int) $params['id'];

    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        api_error('Utilisateur introuvable', 404);
    }

    $stmt = db()->prepare("SELECT * FROM transporteurs WHERE user_id = ?");
    $stmt->execute([$id]);
    $transporteur = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transporteur) {
        api_error('Cet utilisateur n\'est pas transporteur', 404);
    }
    $transporteur['photo_vehicule_url'] = file_url($transporteur['photo_vehicule'] ?? null);
    unset($transporteur['photo_vehicule'], $transporteur['solde']);

    // Statistiques
    $stmt = db()->prepare("SELECT AVG(note) AS note_moyenne, COUNT(*) AS nb_avis FROM avis WHERE transporteur_id = ? AND statut = 'approuve'");
    $stmt->execute([$id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = db()->prepare(
        "SELECT COUNT(*) AS nb_colis FROM reservations r
         WHERE r.statut = 'accepte'
           AND r.voyage_id IN (SELECT id FROM voyages WHERE user_id = ?)"
    );
    $stmt->execute([$id]);
    $nb_colis = (int) $stmt->fetchColumn();

    // Ses voyages approuvés à venir
    $stmt = db()->prepare(
        "SELECT * FROM voyages WHERE user_id = ? AND statut = 'approuve' AND date_depart >= CURDATE()
         ORDER BY date_depart ASC"
    );
    $stmt->execute([$id]);
    $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_success([
        'user' => [
            'id' => (int) $user['id'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'photo_url' => file_url($user['photo_profil'] ?? null),
            'date_inscription' => $user['date_inscription'],
        ],
        'transporteur' => $transporteur,
        'stats' => [
            'note_moyenne' => $stats['note_moyenne'] !== null ? round((float) $stats['note_moyenne'], 2) : null,
            'nb_avis' => (int) $stats['nb_avis'],
            'nb_colis_transportes' => $nb_colis,
        ],
        'voyages' => $voyages,
    ]);
}

/** Statistiques du transporteur connecté (page transporteurs-statistiques). */
function transporteur_stats(array $params): void
{
    $user = require_auth();
    $uid = (int) $user['id'];

    $stmt = db()->prepare("SELECT solde FROM transporteurs WHERE user_id = ?");
    $stmt->execute([$uid]);
    $solde = (float) ($stmt->fetchColumn() ?: 0);

    $stmt = db()->prepare("SELECT COUNT(*) FROM voyages WHERE user_id = ?");
    $stmt->execute([$uid]);
    $nb_voyages = (int) $stmt->fetchColumn();

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM reservations r
         JOIN voyages v ON v.id = r.voyage_id
         WHERE v.user_id = ? AND r.statut = 'accepte'"
    );
    $stmt->execute([$uid]);
    $nb_reservations = (int) $stmt->fetchColumn();

    $stmt = db()->prepare("SELECT AVG(note) AS note_moyenne, COUNT(*) AS nb_avis FROM avis WHERE transporteur_id = ? AND statut = 'approuve'");
    $stmt->execute([$uid]);
    $avis = $stmt->fetch(PDO::FETCH_ASSOC);

    json_success([
        'solde' => $solde,
        'nb_voyages' => $nb_voyages,
        'nb_reservations_acceptees' => $nb_reservations,
        'note_moyenne' => $avis['note_moyenne'] !== null ? round((float) $avis['note_moyenne'], 2) : null,
        'nb_avis' => (int) $avis['nb_avis'],
    ]);
}

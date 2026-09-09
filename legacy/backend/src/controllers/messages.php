<?php
/**
 * Contrôleur Messagerie :
 * - utilisateur ↔ utilisateur (table `messages`, avec contexte colis/voyage)
 * - utilisateur ↔ administrateur (table `messages_admin`)
 */

// ---------------------------------------------------------------------------
// Utilisateur ↔ utilisateur
// ---------------------------------------------------------------------------

function conversations_list(array $params): void
{
    $user = require_auth();
    $uid = (int) $user['id'];

    $stmt = db()->prepare(
        "SELECT u.id, u.nom, u.prenom, u.photo_profil,
                (SELECT contenu FROM messages m2
                  WHERE (m2.expediteur_id = u.id AND m2.destinataire_id = :me1)
                     OR (m2.expediteur_id = :me2 AND m2.destinataire_id = u.id)
                  ORDER BY m2.date_envoi DESC LIMIT 1) AS dernier_message,
                (SELECT date_envoi FROM messages m3
                  WHERE (m3.expediteur_id = u.id AND m3.destinataire_id = :me3)
                     OR (m3.expediteur_id = :me4 AND m3.destinataire_id = u.id)
                  ORDER BY m3.date_envoi DESC LIMIT 1) AS derniere_date,
                (SELECT COUNT(*) FROM messages m4
                  WHERE m4.expediteur_id = u.id AND m4.destinataire_id = :me5 AND m4.lu = 0) AS non_lus
         FROM users u
         WHERE u.id != :me6
           AND EXISTS (SELECT 1 FROM messages m5
                        WHERE (m5.expediteur_id = u.id AND m5.destinataire_id = :me7)
                           OR (m5.expediteur_id = :me8 AND m5.destinataire_id = u.id))
         ORDER BY derniere_date DESC"
    );
    $stmt->execute(array_fill_keys(['me1','me2','me3','me4','me5','me6','me7','me8'], $uid));
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($conversations as &$c) {
        $c['photo_url'] = file_url($c['photo_profil']);
        unset($c['photo_profil']);
        $c['non_lus'] = (int) $c['non_lus'];
    }
    unset($c);

    json_success($conversations);
}

function messages_list(array $params): void
{
    $user = require_auth();
    $uid = (int) $user['id'];

    $destinataireId = query_int('destinataire_id');
    $colisId  = query_int('colis_id') ?: null;
    $voyageId = query_int('voyage_id') ?: null;

    if ($destinataireId <= 0) {
        api_error('destinataire_id est obligatoire');
    }

    $where  = "((m.expediteur_id = ? AND m.destinataire_id = ?) OR (m.expediteur_id = ? AND m.destinataire_id = ?))";
    $params_sql = [$uid, $destinataireId, $destinataireId, $uid];
    if ($colisId) {
        $where .= " AND m.colis_id = ?";
        $params_sql[] = $colisId;
    } elseif ($voyageId) {
        $where .= " AND m.voyage_id = ?";
        $params_sql[] = $voyageId;
    }

    $stmt = db()->prepare(
        "SELECT m.*, u.nom, u.prenom, u.photo_profil
         FROM messages m
         LEFT JOIN users u ON m.expediteur_id = u.id
         WHERE $where
         ORDER BY m.date_envoi ASC
         LIMIT 500"
    );
    $stmt->execute($params_sql);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($messages as &$m) {
        $m['fichier_url'] = file_url($m['fichier'] ?? null);
        $m['photo_url'] = file_url($m['photo_profil'] ?? null);
        unset($m['fichier'], $m['photo_profil']);
    }
    unset($m);

    // Marquer comme lus les messages reçus
    db()->prepare("UPDATE messages SET lu = 1 WHERE expediteur_id = ? AND destinataire_id = ? AND lu = 0")
        ->execute([$destinataireId, $uid]);

    json_success($messages);
}

function messages_send(array $params): void
{
    $user = require_auth();
    $uid = (int) $user['id'];

    $destinataireId = input_int('destinataire_id');
    $contenu  = input_str('contenu');
    $colisId  = input_int('colis_id') ?: null;
    $voyageId = input_int('voyage_id') ?: null;

    if ($destinataireId <= 0 || $destinataireId === $uid) {
        api_error('Destinataire invalide');
    }
    $stmt = db()->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$destinataireId]);
    if (!$stmt->fetch()) {
        api_error('Destinataire introuvable', 404);
    }
    if ($contenu === '' && empty($_FILES['fichier'])) {
        api_error('Message vide');
    }
    if (mb_strlen($contenu) > 5000) {
        api_error('Message trop long (5000 caractères max)');
    }

    $fichier = handle_image_upload($_FILES['fichier'] ?? [], 'messages', 'msg');

    db()->prepare(
        "INSERT INTO messages (expediteur_id, destinataire_id, contenu, fichier, date_envoi, colis_id, voyage_id)
         VALUES (?, ?, ?, ?, NOW(), ?, ?)"
    )->execute([$uid, $destinataireId, $contenu, $fichier, $colisId, $voyageId]);

    json_success(['message' => 'Message envoyé'], 201);
}

// ---------------------------------------------------------------------------
// Utilisateur ↔ administrateur
// ---------------------------------------------------------------------------

/**
 * Conversation avec l'admin.
 * - utilisateur connecté : sa conversation avec l'admin
 * - admin : conversation avec l'utilisateur donné (?user_id=)
 */
function adminchat_show(array $params): void
{
    $user = require_auth();
    $uid = (int) $user['id'];
    $isAdmin = $user['role'] === 'admin';

    if ($isAdmin) {
        $counterpart = query_int('user_id');
        if ($counterpart <= 0) {
            api_error('user_id est obligatoire pour un admin');
        }
    } else {
        $counterpart = get_admin_id();
        if (!$counterpart) {
            json_success([]);
        }
    }

    $stmt = db()->prepare(
        "SELECT m.*, u.nom, u.prenom
         FROM messages_admin m
         JOIN users u ON m.expediteur_id = u.id
         WHERE (m.expediteur_id = :a AND m.destinataire_id = :b)
            OR (m.expediteur_id = :b2 AND m.destinataire_id = :a2)
         ORDER BY m.date_envoi ASC
         LIMIT 500"
    );
    $stmt->execute(['a' => $uid, 'b' => $counterpart, 'a2' => $uid, 'b2' => $counterpart]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($messages as &$m) {
        $m['moi'] = ((int) $m['expediteur_id'] === $uid);
    }
    unset($m);

    // Marquer comme lus
    db()->prepare("UPDATE messages_admin SET lu = 1 WHERE expediteur_id = ? AND destinataire_id = ? AND lu = 0")
        ->execute([$counterpart, $uid]);

    json_success($messages);
}

function adminchat_send(array $params): void
{
    $user = require_auth();
    $uid = (int) $user['id'];
    $isAdmin = $user['role'] === 'admin';

    $contenu = input_str('contenu');
    if ($contenu === '' || mb_strlen($contenu) > 5000) {
        api_error('Message vide ou trop long (5000 caractères max)');
    }

    if ($isAdmin) {
        $destinataire = input_int('destinataire_id');
        $stmt = db()->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$destinataire]);
        if ($destinataire <= 0 || !$stmt->fetch()) {
            api_error('Destinataire invalide');
        }
    } else {
        $destinataire = get_admin_id();
        if (!$destinataire) {
            api_error('Aucun administrateur disponible', 409);
        }
    }

    db()->prepare("INSERT INTO messages_admin (expediteur_id, destinataire_id, contenu) VALUES (?, ?, ?)")
        ->execute([$uid, $destinataire, $contenu]);

    json_success(['message' => 'Message envoyé'], 201);
}

/** Liste des conversations utilisateur ↔ admin (vue admin). */
function adminchat_conversations(array $params): void
{
    $admin = require_admin();
    $adminId = (int) $admin['id'];

    $stmt = db()->prepare(
        "SELECT u.id AS user_id, u.nom, u.prenom, u.email, u.photo_profil,
                (SELECT contenu FROM messages_admin m2
                  WHERE (m2.expediteur_id = u.id AND m2.destinataire_id = :a1)
                     OR (m2.expediteur_id = :a2 AND m2.destinataire_id = u.id)
                  ORDER BY m2.date_envoi DESC LIMIT 1) AS dernier_message,
                (SELECT date_envoi FROM messages_admin m3
                  WHERE (m3.expediteur_id = u.id AND m3.destinataire_id = :a3)
                     OR (m3.expediteur_id = :a4 AND m3.destinataire_id = u.id)
                  ORDER BY m3.date_envoi DESC LIMIT 1) AS derniere_date,
                (SELECT COUNT(*) FROM messages_admin m4
                  WHERE m4.expediteur_id = u.id AND m4.destinataire_id = :a5 AND m4.lu = 0) AS non_lus
         FROM users u
         WHERE u.id != :a6 AND u.role != 'admin'
           AND EXISTS (SELECT 1 FROM messages_admin m5
                        WHERE (m5.expediteur_id = u.id AND m5.destinataire_id = :a7)
                           OR (m5.expediteur_id = :a8 AND m5.destinataire_id = u.id))
         ORDER BY derniere_date DESC"
    );
    $stmt->execute(array_fill_keys(['a1','a2','a3','a4','a5','a6','a7','a8'], $adminId));
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($conversations as &$c) {
        $c['photo_url'] = file_url($c['photo_profil']);
        unset($c['photo_profil']);
        $c['non_lus'] = (int) $c['non_lus'];
    }
    unset($c);

    json_success($conversations);
}

<?php
/**
 * AJAX — conversation entre l'utilisateur connecté et l'administrateur.
 * L'identité est prise dans la session (jamais dans les paramètres).
 */
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    http_response_code(403);
    exit('Accès non autorisé');
}

header('Content-Type: text/html; charset=UTF-8');

$userId  = (int) $_SESSION['user_id'];
$adminId = get_admin_id();

if (!$adminId) {
    echo "<div class='message admin'>Aucun administrateur n'est disponible pour le moment.</div>";
    exit;
}

$stmt = $pdo->prepare(
    "SELECT m.*, u.nom, u.prenom
     FROM messages_admin m
     JOIN users u ON m.expediteur_id = u.id
     WHERE (m.expediteur_id = :user AND m.destinataire_id = :admin)
        OR (m.expediteur_id = :admin AND m.destinataire_id = :user)
     ORDER BY m.date_envoi ASC"
);
$stmt->execute(['user' => $userId, 'admin' => $adminId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($messages as $message) {
    $class = ((int) $message['expediteur_id'] === $userId) ? 'user' : 'admin';
    $date  = e($message['date_envoi']);
    echo "<div class='message " . $class . "'><strong>"
        . e($message['prenom'] . ' ' . $message['nom']) . " :</strong> "
        . nl2br(e($message['contenu']))
        . " <small style='opacity:.7'>(" . $date . ")</small></div>";
}

// Marquer comme lus les messages reçus de l'admin
$pdo->prepare("UPDATE messages_admin SET lu = 1 WHERE expediteur_id = ? AND destinataire_id = ? AND lu = 0")
    ->execute([$adminId, $userId]);

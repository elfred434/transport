<?php
/**
 * AJAX — envoi d'un message de l'utilisateur connecté vers l'administrateur.
 * L'expéditeur est toujours l'utilisateur de session (jamais un paramètre client).
 */
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Méthode non autorisée']));
}

if (!is_logged_in()) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Accès non autorisé']));
}

csrf_check();

$adminId = get_admin_id();
if (!$adminId) {
    http_response_code(409);
    exit(json_encode(['success' => false, 'error' => "Aucun administrateur disponible"]));
}

$contenu = trim($_POST['contenu'] ?? '');
if ($contenu === '' || mb_strlen($contenu) > 5000) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Message vide ou trop long (5000 caractères max)']));
}

$stmt = $pdo->prepare(
    "INSERT INTO messages_admin (expediteur_id, destinataire_id, contenu) VALUES (?, ?, ?)"
);
$stmt->execute([(int) $_SESSION['user_id'], $adminId, $contenu]);

echo json_encode(['success' => true]);

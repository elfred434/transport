<?php
/**
 * AJAX (admin) — réponse de l'admin à un utilisateur.
 */
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Méthode non autorisée']));
}

if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Accès non autorisé']));
}

csrf_check();

$destinataireId = (int) ($_POST['destinataire_id'] ?? 0);
$contenu = trim($_POST['contenu'] ?? '');

if ($destinataireId <= 0 || $contenu === '' || mb_strlen($contenu) > 5000) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Destinataire ou message invalide']));
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$destinataireId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'error' => 'Destinataire introuvable']));
}

$stmt = $pdo->prepare(
    "INSERT INTO messages_admin (expediteur_id, destinataire_id, contenu) VALUES (?, ?, ?)"
);
$stmt->execute([(int) $_SESSION['user_id'], $destinataireId, $contenu]);

echo json_encode(['success' => true]);

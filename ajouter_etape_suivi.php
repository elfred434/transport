<?php
/**
 * AJAX — ajout d'une étape de suivi à un colis.
 * Réservé à l'administrateur ou au transporteur affecté au colis
 * (via une réservation sur l'un de ses voyages).
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

$numero_suivi = trim($_POST['numero_suivi'] ?? '');
$statut       = $_POST['statut'] ?? '';
$statuts_valides = ['En attente', 'En cours', 'Livré'];

if ($numero_suivi === '' || !in_array($statut, $statuts_valides, true)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Numéro de suivi ou statut invalide']));
}

$stmt = $pdo->prepare("SELECT id FROM colis WHERE numero_suivi = ?");
$stmt->execute([$numero_suivi]);
$colis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colis) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'error' => 'Colis introuvable']));
}

$colisId = (int) $colis['id'];
$userId  = (int) $_SESSION['user_id'];

if (!is_admin() && !is_transporteur_of_colis($colisId, $userId)) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => "Vous n'êtes pas autorisé à suivre ce colis"]));
}

$pdo->prepare("INSERT INTO suivi_colis (colis_id, statut) VALUES (?, ?)")
    ->execute([$colisId, $statut]);

echo json_encode(['success' => true, 'message' => 'Étape ajoutée.']);

<?php
/**
 * AJAX — messagerie utilisateur ↔ utilisateur (table `messages`).
 * GET  : liste des messages de la conversation (JSON)
 * POST : envoi d'un message (avec pièce jointe image optionnelle)
 */
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_logged_in()) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Accès non autorisé']));
}

$expediteur_id   = (int) $_SESSION['user_id'];
$destinataire_id = isset($_REQUEST['destinataire_id']) ? (int) $_REQUEST['destinataire_id'] : 0;
$colis_id        = isset($_REQUEST['colis_id']) ? (int) $_REQUEST['colis_id'] : null;
$voyage_id       = isset($_REQUEST['voyage_id']) ? (int) $_REQUEST['voyage_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contenu'])) {
    csrf_check();

    $contenu = trim($_POST['contenu']);

    // Le destinataire doit exister et être différent de l'expéditeur
    if ($destinataire_id <= 0 || $destinataire_id === $expediteur_id) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'error' => 'Destinataire invalide']));
    }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$destinataire_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Destinataire introuvable']));
    }

    if (mb_strlen($contenu) > 5000) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'error' => 'Message trop long (5000 caractères max)']));
    }

    // Pièce jointe : images uniquement, type MIME réel vérifié
    $fichier = null;
    try {
        $fichier = handle_image_upload($_FILES['fichier'] ?? [], 'messages', 'msg');
    } catch (RuntimeException $e) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }

    if ($contenu !== '' || $fichier) {
        $sql = "INSERT INTO messages (expediteur_id, destinataire_id, contenu, fichier, date_envoi, colis_id, voyage_id)
                VALUES (?, ?, ?, ?, NOW(), ?, ?)";
        $pdo->prepare($sql)->execute([
            $expediteur_id, $destinataire_id, $contenu, $fichier, $colis_id, $voyage_id
        ]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// --- GET : chargement de la conversation ---
$where  = "((m.expediteur_id = ? AND m.destinataire_id = ?) OR (m.expediteur_id = ? AND m.destinataire_id = ?))";
$params = [$expediteur_id, $destinataire_id, $destinataire_id, $expediteur_id];

if ($colis_id) {
    $where .= " AND m.colis_id = ?";
    $params[] = $colis_id;
} elseif ($voyage_id) {
    $where .= " AND m.voyage_id = ?";
    $params[] = $voyage_id;
}

$stmt = $pdo->prepare("SELECT m.*, u.nom, u.prenom, u.photo_profil FROM messages m
    LEFT JOIN users u ON m.expediteur_id = u.id
    WHERE $where
    ORDER BY m.date_envoi ASC");
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer comme lus
$pdo->prepare("UPDATE messages SET lu = 1 WHERE expediteur_id = ? AND destinataire_id = ? AND lu = 0")
    ->execute([$destinataire_id, $expediteur_id]);

echo json_encode($messages);

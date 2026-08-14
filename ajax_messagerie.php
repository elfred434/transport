<?php

session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }
$host = 'localhost';
$db = 'transport_db';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    http_response_code(500); exit;
}

$expediteur_id = $_SESSION['user_id'];
$destinataire_id = isset($_REQUEST['destinataire_id']) ? intval($_REQUEST['destinataire_id']) : 0;
$colis_id = isset($_REQUEST['colis_id']) ? intval($_REQUEST['colis_id']) : null;
$voyage_id = isset($_REQUEST['voyage_id']) ? intval($_REQUEST['voyage_id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contenu'])) {
    $contenu = trim($_POST['contenu']);
    $fichier = null;


    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/messages/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('msg_').'.'.$ext;
        $filepath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['fichier']['tmp_name'], $filepath)) {
            $fichier = $filepath;
        }
    }

    if ($contenu != '' || $fichier) {
        $sql = "INSERT INTO messages (expediteur_id, destinataire_id, contenu, fichier, date_envoi, colis_id, voyage_id)
                VALUES (?, ?, ?, ?, NOW(), ?, ?)";
        $pdo->prepare($sql)->execute([
            $expediteur_id, $destinataire_id, $contenu, $fichier, $colis_id, $voyage_id
        ]);
    }
    echo json_encode(['success' => true]);
    exit;
}


$where = "((m.expediteur_id = ? AND m.destinataire_id = ?) OR (m.expediteur_id = ? AND m.destinataire_id = ?))";
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
header('Content-Type: application/json');
echo json_encode($messages);
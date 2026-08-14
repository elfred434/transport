<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$host = 'localhost';
$db = 'transport_db';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    http_response_code(500);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contenu'])) {
    $contenu = trim($_POST['contenu']);
    if ($contenu !== '') {
        $stmt = $pdo->prepare("INSERT INTO messages_admin (user_id, contenu) VALUES (?, ?)");
        $stmt->execute([$user_id, $contenu]);
        echo json_encode(['success' => true, 'contenu' => htmlspecialchars($contenu)]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false]);
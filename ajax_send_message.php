<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit('Accès non autorisé');
}

$host = 'localhost';
$db = 'transport_db';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die('Erreur de connexion à la base de données');
}

$expediteur_id = $_POST['expediteur_id'];
$destinataire_id = $_POST['destinataire_id'];
$contenu = htmlspecialchars(trim($_POST['contenu']));

$stmt = $pdo->prepare("INSERT INTO messages_admin (expediteur_id, destinataire_id, contenu) VALUES (?, ?, ?)");
$stmt->execute([$expediteur_id, $destinataire_id, $contenu]);
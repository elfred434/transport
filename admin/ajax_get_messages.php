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

$user_id = $_GET['user_id'];
$is_admin = $_GET['is_admin'] === 'true';
$admin_id = 1; // ID de l'administrateur (à adapter)

$stmt = $pdo->prepare("
    SELECT m.*, u.nom, u.prenom 
    FROM messages_admin m
    JOIN users u ON m.expediteur_id = u.id
    WHERE (m.expediteur_id = :user_id AND m.destinataire_id = :admin_id)
       OR (m.expediteur_id = :admin_id AND m.destinataire_id = :user_id)
    ORDER BY m.date_envoi ASC
");
$stmt->execute(['user_id' => $user_id, 'admin_id' => $admin_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($messages as $message) {
    $class = $message['expediteur_id'] == $user_id ? 'user' : 'admin';
    echo "<div class='message $class'><strong>{$message['prenom']} {$message['nom']}:</strong> {$message['contenu']}</div>";
}
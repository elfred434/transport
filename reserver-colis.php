<?php

session_start();
if (!isset($_COOKIE['transporteur']) || $_COOKIE['transporteur'] != '1' || !isset($_COOKIE['voyage_id'])) {
    header('Location: login.html');
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
    die('Erreur de connexion à la base de données');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['colis_id']) && isset($_POST['voyage_id'])) {
    $colis_id = intval($_POST['colis_id']);
    $voyage_id = intval($_POST['voyage_id']);

    
    $stmt = $pdo->prepare("SELECT id FROM reservations WHERE colis_id = ? AND voyage_id = ?");
    $stmt->execute([$colis_id, $voyage_id]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO reservations (colis_id, voyage_id, statut) VALUES (?, ?, 'en_attente')");
        $stmt->execute([$colis_id, $voyage_id]);
    }
    header('Location: dashboard.php?msg=reservation_ok');
    exit;
}
header('Location: colis.php');
exit;
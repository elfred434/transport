<?php

session_start();
$host = 'localhost';
$db = 'transport_db';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_suivi = $_POST['numero_suivi'];
    $statut = $_POST['statut'];
    $stmt = $pdo->prepare("SELECT id FROM colis WHERE numero_suivi = ?");
    $stmt->execute([$numero_suivi]);
    $colis = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($colis) {
        $stmt = $pdo->prepare("INSERT INTO suivi_colis (colis_id, statut) VALUES (?, ?)");
        $stmt->execute([$colis['id'], $statut]);
        echo "Étape ajoutée.";
    } else {
        echo "Colis introuvable.";
    }
}
?>
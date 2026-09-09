<?php
/**
 * Réservation d'un colis par un transporteur pour l'un de SES voyages.
 * Authentification par session (les cookies client ont été supprimés :
 * ils étaient falsifiables).
 */
require_once __DIR__ . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: colis.php');
    exit;
}

csrf_check();

$user_id   = (int) $_SESSION['user_id'];
$colis_id  = (int) ($_POST['colis_id'] ?? 0);
$voyage_id = (int) ($_POST['voyage_id'] ?? ($_SESSION['voyage_id'] ?? 0));

if ($colis_id <= 0 || $voyage_id <= 0) {
    $_SESSION['error'] = 'Réservation invalide.';
    header('Location: colis.php');
    exit;
}

// Le voyage doit appartenir à l'utilisateur connecté
$stmt = $pdo->prepare("SELECT id, statut FROM voyages WHERE id = ? AND user_id = ?");
$stmt->execute([$voyage_id, $user_id]);
$voyage = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$voyage) {
    $_SESSION['error'] = "Ce voyage ne vous appartient pas.";
    header('Location: colis.php');
    exit;
}

// Le colis doit exister et être approuvé
$stmt = $pdo->prepare("SELECT id, poids FROM colis WHERE id = ? AND statut = 'approuve'");
$stmt->execute([$colis_id]);
$colis = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$colis) {
    $_SESSION['error'] = "Colis introuvable ou non approuvé.";
    header('Location: colis.php');
    exit;
}

// Éviter les doublons
$stmt = $pdo->prepare("SELECT id FROM reservations WHERE colis_id = ? AND voyage_id = ?");
$stmt->execute([$colis_id, $voyage_id]);
if ($stmt->fetch()) {
    $_SESSION['error'] = 'Vous avez déjà réservé ce colis pour ce voyage.';
    header('Location: colis-detail.php?id=' . $colis_id);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO reservations (colis_id, voyage_id, statut) VALUES (?, ?, 'en_attente')");
$stmt->execute([$colis_id, $voyage_id]);

$_SESSION['success'] = 'Votre réservation a été envoyée au propriétaire du colis.';
header('Location: dashboard.php?msg=reservation_ok');
exit;

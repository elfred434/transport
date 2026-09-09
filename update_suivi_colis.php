<?php
/**
 * Mise à jour du suivi d'un colis par le transporteur affecté (ou l'admin).
 * Le statut "Livré" déclenche une demande de confirmation à l'administrateur.
 */
require_once __DIR__ . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['colis_id'], $_POST['statut'])) {
    header('Location: dashboard.php');
    exit;
}

csrf_check();

$colis_id = (int) $_POST['colis_id'];
$statut   = $_POST['statut'];
$user_id  = (int) $_SESSION['user_id'];

$statuts_valides = ['En attente', 'En cours', 'Livré'];
if (!in_array($statut, $statuts_valides, true)) {
    $_SESSION['error'] = 'Statut invalide.';
    header('Location: dashboard.php');
    exit;
}

// Autorisation : admin ou transporteur affecté à ce colis
if (!is_admin() && !is_transporteur_of_colis($colis_id, $user_id)) {
    $_SESSION['error'] = "Vous n'avez pas les droits pour modifier le suivi de ce colis.";
    header('Location: dashboard.php');
    exit;
}

// Vérifier si le colis est déjà livré et confirmé
$stmt = $pdo->prepare("SELECT statut, confirme_par_admin FROM suivi_colis WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1");
$stmt->execute([$colis_id]);
$last_status = $stmt->fetch(PDO::FETCH_ASSOC);

if ($last_status && $last_status['statut'] === 'Livré' && $last_status['confirme_par_admin']) {
    $_SESSION['error'] = "Impossible de modifier le statut d'un colis déjà livré et confirmé.";
    header('Location: dashboard.php');
    exit;
}

if ($statut === 'Livré') {
    // Demande de confirmation de livraison envoyée à l'administrateur
    $stmt = $pdo->prepare("INSERT INTO suivi_colis (colis_id, statut, demande_livraison) VALUES (?, ?, TRUE)");
    $stmt->execute([$colis_id, $statut]);
    $_SESSION['success'] = "Demande de confirmation de livraison envoyée à l'administrateur.";
} else {
    $stmt = $pdo->prepare("INSERT INTO suivi_colis (colis_id, statut) VALUES (?, ?)");
    $stmt->execute([$colis_id, $statut]);
    $_SESSION['success'] = "Statut du colis mis à jour avec succès.";
}

header('Location: dashboard.php');
exit;

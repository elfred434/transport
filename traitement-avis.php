<?php
require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $transporteur_id = intval($_POST['transporteur_id']);
    $user_id = $_SESSION['user_id'];
    $note = intval($_POST['note']);
    $commentaire = trim($_POST['commentaire']);
    $colis_id = !empty($_POST['colis_id']) ? intval($_POST['colis_id']) : null;

    
    if ($note < 1 || $note > 5) {
        $_SESSION['error'] = "La note doit être entre 1 et 5 étoiles";
        header("Location: profil-transporteur.php?id=$transporteur_id");
        exit;
    }

    if (empty($commentaire)) {
        $_SESSION['error'] = "Le commentaire ne peut pas être vide";
        header("Location: profil-transporteur.php?id=$transporteur_id");
        exit;
    }

    
    $stmt = $pdo->prepare("SELECT id FROM avis WHERE user_id = ? AND transporteur_id = ?");
    $stmt->execute([$user_id, $transporteur_id]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Vous avez déjà posté un avis pour ce transporteur";
        header("Location: profil-transporteur.php?id=$transporteur_id");
        exit;
    }

    
    $stmt = $pdo->prepare("INSERT INTO avis 
        (transporteur_id, user_id, colis_id, note, commentaire, date_avis, statut) 
        VALUES (?, ?, ?, ?, ?, NOW(), 'en_attente')");
    $stmt->execute([$transporteur_id, $user_id, $colis_id, $note, $commentaire]);

    $_SESSION['success'] = "Votre avis a été soumis et sera publié après modération";
    header("Location: profil-transporteur.php?id=$transporteur_id");
    exit;
}

header('Location: profil-transporteur.php');
exit;
?>
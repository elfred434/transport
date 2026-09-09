<?php
require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

csrf_check();

$colis_id = $_POST['colis_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$colis_id) {
    $_SESSION['error'] = "Colis introuvable";
    header('Location: dashboard.php');
    exit;
}

try {
    // Vérifier que le colis appartient bien à l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM colis WHERE id = ? AND user_id = ?");
    $stmt->execute([$colis_id, $user_id]);
    $colis = $stmt->fetch();
    
    if (!$colis) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à modifier ce colis";
        header('Location: dashboard.php');
        exit;
    }
    
    // Traitement de l'image (validation MIME centralisée)
    $image_colis = $colis['image_colis'];
    try {
        $nouvelle_image = handle_image_upload($_FILES['image_colis'] ?? [], 'colis', 'colis');
        if ($nouvelle_image) {
            $image_colis = $nouvelle_image;
            // Supprimer l'ancienne image si elle existe (dans uploads/ uniquement)
            if ($colis['image_colis'] && str_starts_with($colis['image_colis'], 'uploads/')
                && is_file(__DIR__ . '/' . $colis['image_colis'])) {
                unlink(__DIR__ . '/' . $colis['image_colis']);
            }
        }
    } catch (RuntimeException $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: dashboard.php');
        exit;
    }
    
    // Validation et recalcul du prix côté serveur (jamais celui du formulaire)
    $poids = (float) str_replace(',', '.', $_POST['poids'] ?? 0);
    if ($poids <= 0 || $poids > 1000) {
        $_SESSION['error'] = "Poids invalide.";
        header('Location: dashboard.php');
        exit;
    }
    $prix_estime = round(max(1000, 1000 + (1000 * $poids)) * 1.2, 2);

    // Mise à jour des données
    $stmt = $pdo->prepare("UPDATE colis SET 
        nom_colis = ?, 
        image_colis = ?, 
        type_produit = ?, 
        nombre_produits = ?, 
        poids = ?, 
        dimensions = ?, 
        pays = ?, 
        ville = ?, 
        date_limite = ?, 
        adresse_depart = ?, 
        adresse_destination = ?, 
        prix_estime = ?
        WHERE id = ?");
    
    $stmt->execute([
        $_POST['nom_colis'],
        $image_colis,
        $_POST['type_produit'],
        $_POST['nombre_produits'],
        $poids,
        $_POST['dimensions'],
        $_POST['pays'],
        $_POST['ville'],
        $_POST['date_limite'],
        $_POST['adresse_depart'],
        $_POST['adresse_destination'],
        $prix_estime,
        $colis_id
    ]);
    
    $_SESSION['success'] = "Colis mis à jour avec succès";
    header('Location: dashboard.php');
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la mise à jour du colis: " . $e->getMessage();
    header('Location: dashboard.php');
    exit;
}
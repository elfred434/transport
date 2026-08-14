<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// Configuration de la base de données (identique à dashboard.php)
$host = 'localhost';
$db = 'transport_db';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

$colis_id = $_POST['colis_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$colis_id) {
    $_SESSION['error'] = "Colis introuvable";
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Vérifier que le colis appartient bien à l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM colis WHERE id = ? AND user_id = ?");
    $stmt->execute([$colis_id, $user_id]);
    $colis = $stmt->fetch();
    
    if (!$colis) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à modifier ce colis";
        header('Location: dashboard.php');
        exit;
    }
    
    // Traitement de l'image
    $image_colis = $colis['image_colis'];
    if (!empty($_FILES['image_colis']['name'])) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = 'colis_' . uniqid() . '.' . pathinfo($_FILES['image_colis']['name'], PATHINFO_EXTENSION);
        $uploadFile = $uploadDir . $fileName;
        
        // Vérifier le type de fichier
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['image_colis']['tmp_name']);
        
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['image_colis']['tmp_name'], $uploadFile)) {
                $image_colis = $uploadFile;
                // Supprimer l'ancienne image si elle existe
                if ($colis['image_colis'] && file_exists($colis['image_colis'])) {
                    unlink($colis['image_colis']);
                }
            } else {
                $_SESSION['error'] = "Erreur lors du téléchargement de l'image";
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $_SESSION['error'] = "Type de fichier non autorisé";
            header('Location: dashboard.php');
            exit;
        }
    }
    
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
        $_POST['poids'],
        $_POST['dimensions'],
        $_POST['pays'],
        $_POST['ville'],
        $_POST['date_limite'],
        $_POST['adresse_depart'],
        $_POST['adresse_destination'],
        $_POST['prix_estime'],
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
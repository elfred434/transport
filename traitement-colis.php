<?php
session_start();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_colis = $_POST['nom-colis'];
    $type_produit = $_POST['type-produit'];
    $nombre_produits = $_POST['nombre-produits'];
    $poids = $_POST['poids'];
    $dimensions = $_POST['dimensions'];
    $pays = $_POST['pays'];
    $ville = $_POST['ville'];
    $date_limite = $_POST['date-limite'];
    $adresse_depart = $_POST['adresse-depart'];
    $adresse_destination = $_POST['adresse-destination'];
    $prix_estime = 1000 + (1000 * floatval($poids));
    if ($prix_estime < 1000) $prix_estime = 1000;
    $prix_final = $prix_estime * 1.2; 

    $image_colis = null;
    if (isset($_FILES['image-colis']) && $_FILES['image-colis']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image-colis']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('colis_') . '.' . $ext;
        $dest = 'uploads/' . $filename;
        if (!is_dir('uploads')) mkdir('uploads');
        move_uploaded_file($_FILES['image-colis']['tmp_name'], $dest);
        $image_colis = $dest;
    }

    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $numero_suivi = !empty($_POST['numero-suivi']) ? trim($_POST['numero-suivi']) : uniqid('COLIS');

    // Commencer une transaction
    $pdo->beginTransaction();

    try {
        // Insérer le colis
        $stmt = $pdo->prepare("INSERT INTO colis (user_id, nom_colis, image_colis, type_produit, nombre_produits, poids, dimensions, pays, ville, date_limite, adresse_depart, adresse_destination, prix_estime, numero_suivi, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')");
        $stmt->execute([$user_id, $nom_colis, $image_colis, $type_produit, $nombre_produits, $poids, $dimensions, $pays, $ville, $date_limite, $adresse_depart, $adresse_destination, $prix_final, $numero_suivi]);
        $colis_id = $pdo->lastInsertId();

        // Créer une transaction de paiement
        $reference_paiement = 'PAY' . uniqid();
        $stmt_paiement = $pdo->prepare("INSERT INTO paiements (user_id, colis_id, montant, reference, statut, date_creation) VALUES (?, ?, ?, ?, 'en_attente', NOW())");
        $stmt_paiement->execute([$user_id, $colis_id, $prix_final, $reference_paiement]);

        // Valider la transaction
        $pdo->commit();

        // Rediriger vers la page de paiement
        header("Location: paiement.php?colis_id=$colis_id&reference=$reference_paiement");
        exit;

    } catch (Exception $e) {
        // En cas d'erreur, annuler la transaction
        $pdo->rollBack();
        die("Une erreur est survenue lors de la création du colis et du paiement.");
    }
}

$colis = $pdo->prepare("SELECT * FROM colis WHERE user_id = ? AND statut = 'approuve' ORDER BY date_post DESC");
$colis->execute([$user_id]);
$colis = $colis->fetchAll(PDO::FETCH_ASSOC);
?>
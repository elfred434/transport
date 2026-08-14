<?php
// filepath: c:\xampp\htdocs\transport\traitement-transporteur.php
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
    
    $numero_permis = $_POST['numero_permis'];
    $vehicule = $_POST['vehicule'];
    $compagnie = $_POST['compagnie'];
    $adresse = $_POST['adresse'];
    $ville = $_POST['ville'];
    $pays = $_POST['pays'];

    
    $photo_vehicule = null;
    if (isset($_FILES['photo_vehicule']) && $_FILES['photo_vehicule']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo_vehicule']['name'], PATHINFO_EXTENSION);
        $photo_vehicule = 'uploads/vehicules/' . uniqid() . '.' . $ext;
        if (!is_dir('uploads/vehicules')) mkdir('uploads/vehicules', 0777, true);
        move_uploaded_file($_FILES['photo_vehicule']['tmp_name'], $photo_vehicule);
    }

    
    $pays_depart = $_POST['pays_depart'];
    $pays_destination = $_POST['pays_destination'];
    $date_depart = $_POST['date_depart'];
    $heure_depart = $_POST['heure_depart'];
    $poids_max = $_POST['poids_max'];
    $email = $_POST['email'];
    $telephone = $_POST['telephone'];
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    
    $stmt = $pdo->prepare("INSERT INTO voyages (user_id, pays_depart, pays_destination, date_depart, heure_depart, poids_max, email, telephone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $pays_depart, $pays_destination, $date_depart, $heure_depart, $poids_max, $email, $telephone]);
    $voyage_id = $pdo->lastInsertId();

    
    $pdo->prepare("UPDATE users SET role = 'transporteur' WHERE id = ?")->execute([$user_id]);

    
    $stmt = $pdo->prepare("SELECT id FROM transporteurs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO transporteurs 
            (user_id, numero_permis, vehicule, compagnie, adresse, ville, pays, photo_vehicule, date_creation) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())")
            ->execute([$user_id, $numero_permis, $vehicule, $compagnie, $adresse, $ville, $pays, $photo_vehicule]);
    }

    
    setcookie('transporteur', '1', time() + 3600*24*30, '/');
    setcookie('voyage_id', $voyage_id, time() + 3600*24*30, '/');

    header('Location: dashboard.php?msg=voyage_ok');
    exit;
}
?>
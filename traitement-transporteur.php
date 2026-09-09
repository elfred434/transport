<?php
/**
 * Traitement du formulaire "devenir transporteur" :
 * propose un voyage, crée la fiche transporteur (1re fois) et passe le rôle à "transporteur".
 * Le tout de façon atomique.
 */
require_once __DIR__ . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: devenir-transporteur.php');
    exit;
}

csrf_check();

$user_id = (int) $_SESSION['user_id'];

// --- Récupération et validation ---
$numero_permis    = trim($_POST['numero_permis'] ?? '');
$vehicule         = trim($_POST['vehicule'] ?? '');
$compagnie        = trim($_POST['compagnie'] ?? '');
$adresse          = trim($_POST['adresse'] ?? '');
$ville            = trim($_POST['ville'] ?? '');
$pays             = trim($_POST['pays'] ?? '');
$pays_depart      = trim($_POST['pays_depart'] ?? '');
$pays_destination = trim($_POST['pays_destination'] ?? '');
$date_depart      = $_POST['date_depart'] ?? '';
$heure_depart     = $_POST['heure_depart'] ?? '';
$poids_max        = (float) str_replace(',', '.', $_POST['poids_max'] ?? 0);
$email            = strtolower(trim($_POST['email'] ?? ''));
$telephone        = trim($_POST['telephone'] ?? '');

$erreurs = [];
if ($numero_permis === '')            $erreurs[] = 'numéro de permis';
if ($vehicule === '')                 $erreurs[] = 'véhicule';
if ($pays_depart === '')              $erreurs[] = 'pays de départ';
if ($pays_destination === '')         $erreurs[] = 'pays de destination';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_depart) || strtotime($date_depart) < strtotime('today')) {
    $erreurs[] = 'date de départ';
}
if (!preg_match('/^\d{2}:\d{2}$/', $heure_depart)) $erreurs[] = 'heure de départ';
if ($poids_max <= 0 || $poids_max > 100000)        $erreurs[] = 'poids maximum';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $erreurs[] = 'email';

if ($erreurs) {
    $_SESSION['error'] = 'Champs invalides : ' . implode(', ', $erreurs) . '.';
    header('Location: devenir-transporteur.php');
    exit;
}

// --- Photo du véhicule (optionnelle, validée) ---
$photo_vehicule = null;
try {
    $photo_vehicule = handle_image_upload($_FILES['photo_vehicule'] ?? [], 'vehicules', 'vehicule');
} catch (RuntimeException $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: devenir-transporteur.php');
    exit;
}

// --- Insertion atomique : voyage + rôle + fiche transporteur ---
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO voyages (user_id, pays_depart, pays_destination, date_depart, heure_depart, poids_max, email, telephone)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$user_id, $pays_depart, $pays_destination, $date_depart, $heure_depart, $poids_max, $email, $telephone]);
    $voyage_id = (int) $pdo->lastInsertId();

    // Passer transporteur (sans jamais écraser le rôle admin)
    $pdo->prepare("UPDATE users SET role = 'transporteur' WHERE id = ? AND role = 'utilisateur'")
        ->execute([$user_id]);

    $stmt = $pdo->prepare("SELECT id FROM transporteurs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        $pdo->prepare(
            "INSERT INTO transporteurs
                (user_id, numero_permis, vehicule, compagnie, adresse, ville, pays, photo_vehicule, date_creation)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute([$user_id, $numero_permis, $vehicule, $compagnie, $adresse, $ville, $pays, $photo_vehicule]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Erreur devenir-transporteur : ' . $e->getMessage());
    $_SESSION['error'] = 'Une erreur est survenue lors de la proposition de voyage.';
    header('Location: devenir-transporteur.php');
    exit;
}

// Contexte transporteur en SESSION (jamais dans des cookies modifiables par le client)
$_SESSION['is_transporteur'] = true;
$_SESSION['voyage_id']       = $voyage_id;
$_SESSION['role']            = $_SESSION['role'] === 'admin' ? 'admin' : 'transporteur';
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    $_SESSION['user_role'] = 'transporteur';
}

$_SESSION['success'] = 'Votre voyage a été proposé avec succès.';
header('Location: dashboard.php?msg=voyage_ok');
exit;

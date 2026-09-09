<?php
/**
 * Traitement du formulaire "poster un colis" :
 * crée le colis (avec numéro de suivi unique) et le paiement associé,
 * de façon atomique, puis redirige vers la page de paiement.
 */
require_once __DIR__ . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: poster-colis.php');
    exit;
}

csrf_check();

$user_id = (int) $_SESSION['user_id'];

// --- Validation des entrées ---
$nom_colis     = trim($_POST['nom-colis'] ?? '');
$type_produit  = $_POST['type-produit'] ?? '';
$types_valides = ['alimentaire', 'electronique', 'vetements', 'documents', 'autre'];
$nombre        = (int) ($_POST['nombre-produits'] ?? 0);
$poids         = (float) str_replace(',', '.', $_POST['poids'] ?? 0);
$dimensions    = trim($_POST['dimensions'] ?? '');
$pays          = trim($_POST['pays'] ?? '');
$ville         = trim($_POST['ville'] ?? '');
$date_limite   = $_POST['date-limite'] ?? '';
$adresse_depart      = trim($_POST['adresse-depart'] ?? '');
$adresse_destination = trim($_POST['adresse-destination'] ?? '');

$erreurs = [];
if ($nom_colis === '' || mb_strlen($nom_colis) > 150) $erreurs[] = 'nom du colis';
if (!in_array($type_produit, $types_valides, true))   $erreurs[] = 'type de produit';
if ($nombre < 1 || $nombre > 10000)                   $erreurs[] = 'nombre de produits';
if ($poids <= 0 || $poids > 1000)                     $erreurs[] = 'poids';
if ($pays === '' || $ville === '')                    $erreurs[] = 'destination';
if ($adresse_depart === '' || $adresse_destination === '') $erreurs[] = 'adresses';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_limite) || strtotime($date_limite) < strtotime('today')) {
    $erreurs[] = 'date limite';
}

if ($erreurs) {
    $_SESSION['error'] = 'Champs invalides : ' . implode(', ', $erreurs) . '.';
    header('Location: poster-colis.php');
    exit;
}

// --- Image du colis (optionnelle, validée) ---
$image_colis = null;
try {
    $image_colis = handle_image_upload($_FILES['image-colis'] ?? [], 'colis', 'colis');
} catch (RuntimeException $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: poster-colis.php');
    exit;
}

// --- Prix calculé côté serveur ---
$prix_estime = 1000 + (1000 * $poids);
if ($prix_estime < 1000) $prix_estime = 1000;
$prix_final = round($prix_estime * 1.2, 2);

// --- Numéro de suivi unique ---
do {
    $numero_suivi = 'COLIS' . strtoupper(bin2hex(random_bytes(5)));
    $stmt = $pdo->prepare("SELECT id FROM colis WHERE numero_suivi = ?");
    $stmt->execute([$numero_suivi]);
} while ($stmt->fetch());

// --- Création atomique colis + paiement ---
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO colis (user_id, nom_colis, image_colis, type_produit, nombre_produits, poids,
                            dimensions, pays, ville, date_limite, adresse_depart, adresse_destination,
                            prix_estime, numero_suivi, statut)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')"
    );
    $stmt->execute([
        $user_id, $nom_colis, $image_colis, $type_produit, $nombre, $poids,
        $dimensions, $pays, $ville, $date_limite, $adresse_depart, $adresse_destination,
        $prix_final, $numero_suivi,
    ]);
    $colis_id = (int) $pdo->lastInsertId();

    $reference_paiement = 'PAY' . strtoupper(bin2hex(random_bytes(6)));
    $stmt = $pdo->prepare(
        "INSERT INTO paiements (user_id, colis_id, montant, reference, statut, date_creation)
         VALUES (?, ?, ?, ?, 'en_attente', NOW())"
    );
    $stmt->execute([$user_id, $colis_id, $prix_final, $reference_paiement]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Erreur création colis/paiement : ' . $e->getMessage());
    $_SESSION['error'] = 'Une erreur est survenue lors de la création du colis.';
    header('Location: poster-colis.php');
    exit;
}

header("Location: paiement.php?colis_id=$colis_id&reference=" . urlencode($reference_paiement));
exit;

<?php
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.html');
//     exit;
// }

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['colis_id'], $_POST['statut'])) {
//     $colis_id = intval($_POST['colis_id']);
//     $statut = $_POST['statut'];
    
//     $host = 'localhost';
//     $db = 'transport_db';
//     $user = 'root';
//     $pass = '';
//     $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
//     $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
//     // Vérifier si le colis est déjà livré
//     $stmt = $pdo->prepare("SELECT statut FROM suivi_colis WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1");
//     $stmt->execute([$colis_id]);
//     $last_status = $stmt->fetch(PDO::FETCH_ASSOC);
    
//     if ($last_status && $last_status['statut'] === 'Livré') {
//         $_SESSION['error'] = "Impossible de modifier le statut d'un colis déjà livré";
//         header('Location: dashboard.php');
//         exit;
//     }
    
    
//     // $stmt = $pdo->prepare("SELECT r.voyage_id FROM reservations r 
//     //                       JOIN voyages v ON r.voyage_id = v.id 
//     //                       WHERE r.colis_id = ? AND v.user_id = ?");
//     // $stmt->execute([$colis_id, $_SESSION['user_id']]);
//     // $reservation = $stmt->fetch();
    
//     // if (!$reservation) {
//     //     $_SESSION['error'] = "Vous n'avez pas les droits pour modifier ce colis";
//     //     header('Location: dashboard.php');
//     //     exit;
//     // }
    
    
//     $stmt = $pdo->prepare("INSERT INTO suivi_colis (colis_id, statut) VALUES (?, ?)");
//     $stmt->execute([$colis_id, $statut]);
    
    
//     if ($statut === 'Livré') {
//         $stmt = $pdo->prepare("UPDATE reservations SET statut = 'termine' WHERE colis_id = ? AND voyage_id = ?");
//         $stmt->execute([$colis_id, $reservation['voyage_id']]);
//     }
    
//     $_SESSION['success'] = "Statut du colis mis à jour avec succès";
// }

// header('Location: dashboard.php');
// exit;

?>
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['colis_id'], $_POST['statut'])) {
    $colis_id = intval($_POST['colis_id']);
    $statut = $_POST['statut'];
    
    $host = 'localhost';
    $db = 'transport_db';
    $user = 'root';
    $pass = '';
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Vérifier si le colis est déjà livré et confirmé
    $stmt = $pdo->prepare("SELECT statut, confirme_par_admin FROM suivi_colis WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1");
    $stmt->execute([$colis_id]);
    $last_status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_status && $last_status['statut'] === 'Livré' && $last_status['confirme_par_admin']) {
        $_SESSION['error'] = "Impossible de modifier le statut d'un colis déjà livré et confirmé";
        header('Location: dashboard.php');
        exit;
    }
    
    // Pour le statut "Livré", on marque comme demande de livraison
    if ($statut === 'Livré') {
        $stmt = $pdo->prepare("INSERT INTO suivi_colis (colis_id, statut, demande_livraison) VALUES (?, ?, TRUE)");
        $stmt->execute([$colis_id, $statut]);
        $_SESSION['success'] = "Demande de confirmation de livraison envoyée à l'administrateur";
    } else {
        // Pour les autres statuts, mise à jour normale
        $stmt = $pdo->prepare("INSERT INTO suivi_colis (colis_id, statut, confirme_par_admin) VALUES (?, ?, TRUE)");
        $stmt->execute([$colis_id, $statut]);
        $_SESSION['success'] = "Statut du colis mis à jour avec succès";
    }
}

header('Location: dashboard.php');
exit;
?>
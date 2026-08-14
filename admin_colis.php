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


if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'approuver') {
        $pdo->prepare("UPDATE colis SET statut = 'approuve' WHERE id = ?")->execute([$id]);
    }
    if ($_GET['action'] === 'refuser') {
        $pdo->prepare("UPDATE colis SET statut = 'refuse' WHERE id = ?")->execute([$id]);
    }
    header('Location: admin_colis.php');
    exit;
}


$colis = $pdo->query("SELECT * FROM colis WHERE statut = 'en_attente' ORDER BY date_post DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin-colis.css">
    <title>Admin - Approbation des colis</title>
    
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h2>Colis en attente d'approbation</h2>
        <table>
            <tr>
                <th>Nom</th>
                <th>Image</th>
                <th>Type</th>
                <th>Poids</th>
                <th>Destination</th>
                <th>Date limite</th>
                <th>Action</th>
            </tr>
            <?php foreach($colis as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['nom_colis']) ?></td>
                <td>
                    <?php if ($c['image_colis']): ?>
                        <img src="<?= htmlspecialchars($c['image_colis']) ?>" class="avatar">
                    <?php else: ?>
                        <img src="assets/default-colis.png" class="avatar">
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($c['type_produit']) ?></td>
                <td><?= htmlspecialchars($c['poids']) ?></td>
                <td><?= htmlspecialchars($c['pays']) ?>, <?= htmlspecialchars($c['ville']) ?></td>
                <td><?= htmlspecialchars($c['date_limite']) ?></td>
                <td>
                    <a href="?action=approuver&id=<?= $c['id'] ?>" class="btn btn-approve">Approuver</a>
                    <a href="?action=refuser&id=<?= $c['id'] ?>" class="btn btn-refuse">Refuser</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
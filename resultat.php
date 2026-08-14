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

if (!isset($_GET['colis_id'])) {
    die("Aucun colis sélectionné.");
}
$colis_id = intval($_GET['colis_id']);


$stmt = $pdo->prepare("SELECT * FROM colis WHERE id = ?");
$stmt->execute([$colis_id]);
$colis = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$colis) die("Colis introuvable.");


$stmt = $pdo->prepare("SELECT v.*, u.nom, u.prenom, u.photo_profil 
    FROM voyages v 
    LEFT JOIN users u ON v.user_id = u.id
    WHERE v.pays_destination = ? 
      AND v.poids_max >= ? 
      AND v.date_depart >= CURDATE()
    ORDER BY v.date_depart ASC");
$stmt->execute([$colis['pays'], $colis['poids']]);
$voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats de recherche - Transporteurs disponibles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; margin: 0; }
        .container { max-width: 800px; margin: 40px auto; background: #fff; padding: 32px; border-radius: 10px; box-shadow: 0 2px 8px #0001; }
        h2 { color: #007bff; text-align: center; margin-bottom: 24px; }
        .resume-demande {
            background: #f1f8ff;
            border-left: 4px solid #007bff;
            padding: 18px 20px;
            border-radius: 6px;
            margin-bottom: 28px;
            color: #34495e;
        }
        .transporteurs-list { margin: 0; padding: 0; list-style: none; }
        .transporteur-card {
            display: flex;
            align-items: flex-start;
            gap: 24px;
            background: #f9fafb;
            border: 1px solid #e0e6ed;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 4px #0001;
        }
        .transporteur-avatar {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: #e6f0ff;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; color: #007bff;
            overflow: hidden;
        }
        .transporteur-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .transporteur-infos { flex: 1; }
        .transporteur-infos h3 { margin: 0 0 8px 0; color: #007bff; font-size: 20px; }
        .infos-row { margin-bottom: 6px; color: #34495e; }
        .infos-row i { margin-right: 7px; color: #007bff; }
        .tarif { font-weight: bold; color: #27ae60; font-size: 18px; margin-bottom: 8px; }
        .btn-action {
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 22px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-action:hover { background: #0056b3; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
<div class="content">
    <div class="container">
        <h2><i class="fa-solid fa-magnifying-glass"></i> Résultats de recherche</h2>

        <div class="resume-demande">
            <strong>Colis :</strong> <?= htmlspecialchars($colis['nom_colis']) ?>, <?= htmlspecialchars($colis['poids']) ?> kg<br>
            <strong>Destination :</strong> <?= htmlspecialchars($colis['pays']) ?>, <?= htmlspecialchars($colis['ville']) ?><br>
            <strong>Date limite :</strong> <?= htmlspecialchars($colis['date_limite']) ?>
        </div>

        <ul class="transporteurs-list">
            <?php if (count($voyages) == 0): ?>
                <li>Aucun transporteur disponible pour ce colis.</li>
            <?php endif; ?>
            <?php foreach($voyages as $v): ?>
            <li class="transporteur-card">
                <div class="transporteur-avatar">
                    <?php if ($v['photo_profil'] && file_exists($v['photo_profil'])): ?>
                        <img src="<?= htmlspecialchars($v['photo_profil']) ?>" alt="Profil">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="transporteur-infos">
                    <h3><?= htmlspecialchars($v['prenom'] . ' ' . $v['nom']) ?></h3>
                    <div class="infos-row"><i class="fa-solid fa-plane-departure"></i> Départ : <?= htmlspecialchars($v['pays_depart']) ?></div>
                    <div class="infos-row"><i class="fa-solid fa-plane-arrival"></i> Destination : <?= htmlspecialchars($v['pays_destination']) ?></div>
                    <div class="infos-row"><i class="fa-solid fa-calendar-days"></i> <?= htmlspecialchars($v['date_depart']) ?> à <?= htmlspecialchars($v['heure_depart']) ?></div>
                    <div class="infos-row"><i class="fa-solid fa-weight-hanging"></i> Poids max : <?= htmlspecialchars($v['poids_max']) ?> kg</div>
                    <div class="infos-row"><i class="fa-solid fa-envelope"></i> Email : <?= htmlspecialchars($v['email']) ?></div>
                    <div class="infos-row"><i class="fa-solid fa-phone"></i> Téléphone : <?= htmlspecialchars($v['telephone']) ?></div>
                    
                    <div class="tarif"><i class="fa-solid fa-euro-sign"></i> Tarif estimé : <?= number_format(5 + 2 * floatval($colis['poids']), 2, ',', ' ') ?> €</div>
                    <a href="messagerie.php?destinataire_id=<?= $v['user_id'] ?>&colis_id=<?= $colis['id'] ?>&voyage_id=<?= $v['id'] ?>" class="btn-action">
                        <i class="fa-solid fa-envelope"></i> Contacter
                    </a>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
</body>
</html>
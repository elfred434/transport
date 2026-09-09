<?php

require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];


$stmt = $pdo->prepare("
    SELECT m.*, u.nom, u.prenom
    FROM messages m
    JOIN users u ON (CASE WHEN m.expediteur_id = ? THEN m.destinataire_id ELSE m.expediteur_id END) = u.id
    WHERE m.expediteur_id = ? OR m.destinataire_id = ?
    GROUP BY LEAST(m.expediteur_id, m.destinataire_id), GREATEST(m.expediteur_id, m.destinataire_id), m.colis_id, m.voyage_id
    ORDER BY MAX(m.date_envoi) DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie</title>
    
    
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
</head>
<body>
    <?php include 'menu.php'; ?>
<div class="content">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow border-0 rounded-4">
                    <div class="card-body p-4">
                        <h2 class="text-primary mb-4 text-center"><i class="fa-solid fa-envelope"></i> Mes conversations</h2>
                        <?php if (count($conversations) == 0): ?>
                            <div class="alert alert-info text-center">
                                <i class="fa-solid fa-circle-info"></i> Aucune conversation pour le moment.
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach($conversations as $conv): ?>
                                    <li class="list-group-item d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="fa-solid fa-user text-primary me-2"></i>
                                            <a href="messagerie.php?destinataire_id=<?= ($conv['expediteur_id'] == $user_id ? $conv['destinataire_id'] : $conv['expediteur_id']) ?>&colis_id=<?= $conv['colis_id'] ?>&voyage_id=<?= $conv['voyage_id'] ?>" class="fw-bold text-decoration-none text-primary">
                                                Discussion avec <?= htmlspecialchars($conv['prenom'] . ' ' . $conv['nom']) ?>
                                            </a>
                                        </div>
                                        <span class="text-muted small">
                                            <i class="fa-regular fa-clock"></i>
                                            <?= date('d/m/Y H:i', strtotime($conv['date_envoi'])) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="text-center mt-4">
                            <a href="dashboard.php" class="btn btn-outline-primary">
                                <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
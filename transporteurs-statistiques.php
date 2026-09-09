<?php

require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer les transporteurs avec leurs statistiques
$query = "
    SELECT 
        u.id AS transporteur_id,
        u.nom,
        u.prenom,
        u.photo_profil,
        COUNT(DISTINCT v.id) AS voyages_count,
        COUNT(DISTINCT c.id) AS colis_count,
        IFNULL(ROUND(AVG(a.note), 1), 0) AS note_moyenne
    FROM users u
    LEFT JOIN voyages v ON u.id = v.user_id AND v.statut = 'approuve'
    LEFT JOIN reservations r ON v.id = r.voyage_id AND r.statut = 'accepte'
    LEFT JOIN colis c ON r.colis_id = c.id
    LEFT JOIN avis a ON u.id = a.transporteur_id AND a.statut = 'approuve'
    WHERE u.role = 'transporteur'
    GROUP BY u.id
    ORDER BY note_moyenne DESC, voyages_count DESC, colis_count DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$transporteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Transporteurs - Statistiques</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .transporteur-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .transporteur-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .transporteur-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #e9ecef;
        }

        .transporteur-initials {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #0078d4;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .rating {
            color: #ffc107;
            font-size: 1.25rem;
        }

        .btn-profile {
            background-color: #0078d4;
            color: white;
            font-weight: 500;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            transition: all 0.3s;
        }

        .btn-profile:hover {
            background-color: #005bb5;
            color: white;
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <h1 class="mb-4 text-center">Transporteurs - Statistiques</h1>
        <div class="row">
            <?php if (!empty($transporteurs)): ?>
                <?php foreach ($transporteurs as $transporteur): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="transporteur-card">
                            <div class="d-flex align-items-center mb-3">
                                <?php if ($transporteur['photo_profil']): ?>
                                    <img src="<?= htmlspecialchars($transporteur['photo_profil']) ?>" alt="Photo de profil" class="transporteur-avatar me-3">
                                <?php else: ?>
                                    <div class="transporteur-initials me-3">
                                        <?= strtoupper(substr($transporteur['prenom'], 0, 1) . substr($transporteur['nom'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h5 class="mb-0"><?= htmlspecialchars($transporteur['prenom'] . ' ' . $transporteur['nom']) ?></h5>
                                    <div class="rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i > $transporteur['note_moyenne'] ? '-half-alt' : '' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ms-2"><?= number_format($transporteur['note_moyenne'], 1) ?>/5</span>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-2"><strong>Voyages proposés :</strong> <?= $transporteur['voyages_count'] ?></p>
                            <p class="mb-2"><strong>Colis transportés :</strong> <?= $transporteur['colis_count'] ?></p>
                            <a href="profil-transporteur.php?id=<?= $transporteur['transporteur_id'] ?>" class="btn btn-profile w-100">
                                Voir le profil
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i> Aucun transporteur trouvé.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
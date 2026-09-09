<?php
require_once __DIR__ . '/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Connexion à la base de données
// Initialiser les variables de recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'transporteurs';

// Requête SQL pour la recherche
$results = [];
if ($type === 'transporteurs') {
    $query = "
        SELECT u.id, u.nom, u.prenom, u.photo_profil, t.vehicule, t.ville, t.pays
        FROM users u
        LEFT JOIN transporteurs t ON u.id = t.user_id
        WHERE u.role = 'transporteur' AND (u.nom LIKE ? OR u.prenom LIKE ? OR t.ville LIKE ? OR t.pays LIKE ?)
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute(["%$search%", "%$search%", "%$search%", "%$search%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($type === 'colis') {
    $query = "
        SELECT c.id, c.nom_colis, c.type_produit, c.pays, c.ville, c.poids, c.date_post
        FROM colis c
        WHERE c.nom_colis LIKE ? OR c.type_produit LIKE ? OR c.pays LIKE ? OR c.ville LIKE ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute(["%$search%", "%$search%", "%$search%", "%$search%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche - Transporteurs et Colis</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .search-container {
            margin-top: 50px;
        }
        .result-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        .result-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container search-container">
        <h1 class="text-center mb-4">Recherche - Transporteurs et Colis</h1>
        <form method="GET" action="recherche.php" class="mb-4">
            <div class="row g-2">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" placeholder="Rechercher par nom, ville, pays..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select">
                        <option value="transporteurs" <?= $type === 'transporteurs' ? 'selected' : '' ?>>Transporteurs</option>
                        <option value="colis" <?= $type === 'colis' ? 'selected' : '' ?>>Colis</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Rechercher</button>
                </div>
            </div>
        </form>

        <div class="row">
            <?php if (!empty($results)): ?>
                <?php foreach ($results as $result): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="result-card">
                            <?php if ($type === 'transporteurs'): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <?php if ($result['photo_profil']): ?>
                                        <img src="<?= htmlspecialchars($result['photo_profil']) ?>" alt="Photo de profil" class="result-avatar me-3">
                                    <?php else: ?>
                                        <div class="result-avatar bg-secondary text-white d-flex align-items-center justify-content-center">
                                            <?= strtoupper(substr($result['prenom'], 0, 1) . substr($result['nom'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h5 class="mb-0"><?= htmlspecialchars($result['prenom'] . ' ' . $result['nom']) ?></h5>
                                        <p class="mb-0 text-muted"><?= htmlspecialchars($result['ville'] . ', ' . $result['pays']) ?></p>
                                    </div>
                                </div>
                                <p><strong>Véhicule :</strong> <?= htmlspecialchars($result['vehicule']) ?></p>
                                <a href="profil-transporteur.php?id=<?= $result['id'] ?>" class="btn btn-primary w-100">Voir le profil</a>
                            <?php elseif ($type === 'colis'): ?>
                                <h5 class="mb-2"><?= htmlspecialchars($result['nom_colis']) ?></h5>
                                <p class="mb-1"><strong>Type :</strong> <?= htmlspecialchars($result['type_produit']) ?></p>
                                <p class="mb-1"><strong>Poids :</strong> <?= htmlspecialchars($result['poids']) ?> kg</p>
                                <p class="mb-1"><strong>Lieu :</strong> <?= htmlspecialchars($result['ville'] . ', ' . $result['pays']) ?></p>
                                <p class="mb-1"><strong>Date :</strong> <?= htmlspecialchars($result['date_post']) ?></p>
                                <a href="colis-detail.php?id=<?= $result['id'] ?>" class="btn btn-primary w-100">Voir les détails</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i> Aucun résultat trouvé.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
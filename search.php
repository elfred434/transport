<?php
require_once __DIR__ . '/functions.php';
require_login();

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$colis = $voyages = [];

if ($search !== '') {

    $stmt = $pdo->prepare("SELECT * FROM colis WHERE statut = 'approuve' AND (
        nom_colis LIKE :q OR
        pays LIKE :q OR
        ville LIKE :q OR
        type_produit LIKE :q
    ) ORDER BY date_post DESC");
    $stmt->execute(['q' => "%$search%"]);
    $colis = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $stmt = $pdo->prepare("SELECT * FROM voyages WHERE 
        pays_depart LIKE :q OR
        pays_destination LIKE :q
        ORDER BY date_post DESC");
    $stmt->execute(['q' => "%$search%"]);
    $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Recherche</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <style>
        body { background: #f4f8fb; }
        .card { border-radius: 16px; box-shadow: 0 4px 16px #1a237e11; }
        .search-bar { max-width: 500px; margin: 30px auto 40px auto; }
        .section-title { color: #1a237e; }
    </style>
</head>
<body>
    <?php if (file_exists('menu.php')) include 'menu.php'; ?>
<div class="content">
    <div class="container">
        <form class="search-bar d-flex" method="get" action="search.php">
            <input type="text" class="form-control me-2" name="q" placeholder="Rechercher un colis ou un voyage..." value="<?= htmlspecialchars($search) ?>" autofocus>
            <button class="btn btn-primary" type="submit">Rechercher</button>
        </form>

        <?php if ($search !== ''): ?>
            <h3 class="section-title mb-3">Résultats pour "<?= htmlspecialchars($search) ?>"</h3>

            <h5 class="mt-4 mb-3">Colis trouvés</h5>
            <div class="row g-3 mb-4">
                <?php if ($colis): foreach($colis as $c): ?>
                    <div class="col-md-4">
                        <div class="card p-3">
                            <h6><?= htmlspecialchars($c['nom_colis']) ?></h6>
                            <div class="mb-2 text-muted"><?= htmlspecialchars($c['type_produit']) ?>, <?= htmlspecialchars($c['poids']) ?> kg</div>
                            <div><?= htmlspecialchars($c['ville']) ?>, <?= htmlspecialchars($c['pays']) ?></div>
                            <div class="mt-2">
                                <a href="colis-detail.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm">Voir</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="col-12 text-muted">Aucun colis trouvé.</div>
                <?php endif; ?>
            </div>

            <h5 class="mt-4 mb-3">Voyages trouvés</h5>
            <div class="row g-3 mb-4">
                <?php if ($voyages): foreach($voyages as $v): ?>
                    <div class="col-md-4">
                        <div class="card p-3">
                            <div><strong>Départ :</strong> <?= htmlspecialchars($v['pays_depart']) ?></div>
                            <div><strong>Destination :</strong> <?= htmlspecialchars($v['pays_destination']) ?></div>
                            <div class="mb-2"><strong>Date :</strong> <?= htmlspecialchars($v['date_depart']) ?> à <?= htmlspecialchars($v['heure_depart']) ?></div>
                            <div class="mb-2"><strong>Poids max :</strong> <?= htmlspecialchars($v['poids_max']) ?> kg</div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="col-12 text-muted">Aucun voyage trouvé.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
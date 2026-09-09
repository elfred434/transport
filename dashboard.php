<?php
require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer le solde du transporteur
$stmt = $pdo->prepare("SELECT solde FROM transporteurs WHERE user_id = ?");
$stmt->execute([$user_id]);
$solde = $stmt->fetchColumn();

// Récupération des colis de l'utilisateur
$colis = $pdo->prepare("SELECT c.*, 
    (SELECT COUNT(*) FROM reservations r WHERE r.colis_id = c.id AND r.statut = 'accepte') as nb_reservations
    FROM colis c 
    WHERE c.user_id = ? 
    ORDER BY c.date_post DESC");
$colis->execute([$user_id]);
$colis = $colis->fetchAll(PDO::FETCH_ASSOC);

foreach ($colis as &$c) {
    if ($c['nb_reservations'] > 0) {
        $stmt = $pdo->prepare("SELECT r.id, r.voyage_id, u.id as transporteur_id, u.nom, u.prenom, u.telephone, u.email
            FROM reservations r
            JOIN voyages v ON r.voyage_id = v.id
            JOIN users u ON v.user_id = u.id
            WHERE r.colis_id = ? AND r.statut = 'accepte'");
        $stmt->execute([$c['id']]);
        $c['reservations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $c['reservations'] = [];
    }

    // Récupération du statut de livraison
    $stmt = $pdo->prepare("SELECT statut FROM suivi_colis WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1");
    $stmt->execute([$c['id']]);
    $c['statut_livraison'] = $stmt->fetchColumn();
}
unset($c);

// Récupération des voyages de l'utilisateur
$voyages = $pdo->prepare("SELECT * FROM voyages WHERE user_id = ? AND statut = 'approuve' ORDER BY date_post DESC");
$voyages->execute([$user_id]);
$voyages = $voyages->fetchAll(PDO::FETCH_ASSOC);
$paiements = $pdo->prepare("SELECT p.*, c.nom_colis 
                           FROM paiements p
                           LEFT JOIN colis c ON p.colis_id = c.id
                           WHERE p.user_id = ?
                           ORDER BY p.date_creation DESC");
$paiements->execute([$user_id]);
$paiements = $paiements->fetchAll(PDO::FETCH_ASSOC);

// Statistiques des paiements
$stats_paiements = $pdo->prepare("SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
                                SUM(CASE WHEN statut = 'paye' THEN 1 ELSE 0 END) as payes,
                                SUM(CASE WHEN statut = 'echec' THEN 1 ELSE 0 END) as echecs,
                                SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) as annules,
                                SUM(CASE WHEN statut = 'paye' THEN montant ELSE 0 END) as montant_total
                             FROM paiements 
                             WHERE user_id = ?");
$stats_paiements->execute([$user_id]);
$stats_paiements = $stats_paiements->fetch(PDO::FETCH_ASSOC);
?>
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Mon tableau de bord</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --primary-color: #3498db;
            --secondary-color: #34495e;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        .logo-container {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #34495e;
        }

        .logo {
            max-width: 100%;
            height: auto;
        }

        .menu-items {
            padding: 0;
            list-style-type: none;
        }

        .menu-items li a {
            display: block;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .menu-items li a:hover {
            background-color: #34495e;
            border-left: 4px solid #3498db;
        }

        .menu-items li a i {
            margin-right: 10px;
        }

        /* Sidebar responsive */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background-color: var(--primary-color);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            transition: all 0.3s;
            z-index: 1000;
            overflow-y: auto;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
        }

        .copy-btn {
            transition: all 0.2s;
        }

        .copy-btn:hover {
            background-color: #f8f9fa;
            transform: scale(1.05);
        }

        /* Mobile menu */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: var(--primary-color);
            color: white;
            border: none;
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Tables responsive */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }

        /* Badges */
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-delivered {
            background-color: #28a745;
        }

        .badge-in-progress {
            background-color: #17a2b8;
        }

        /* Mobile bottom menu */
        .mobile-bottom-menu {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: white;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .mobile-bottom-menu ul {
            display: flex;
            justify-content: space-around;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .mobile-bottom-menu li {
            flex: 1;
            text-align: center;
        }

        .mobile-bottom-menu a {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 5px;
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 0.8rem;
        }

        .mobile-bottom-menu i {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            #sidebar {
                transform: translateX(-100%);
            }

            #sidebar.active {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                padding-bottom: 70px !important;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .mobile-bottom-menu {
                display: block;
            }

            .table-responsive table {
                min-width: 600px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 768px) {
            .card-body .row {
                flex-direction: column;
            }

            .card-body .col-md-8,
            .card-body .col-md-4 {
                width: 100%;
            }

            .table img {
                width: 40px;
                height: 40px;
            }

            .mobile-hide {
                display: none;
            }

            h2 {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 576px) {
            .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }

            .modal-body {
                padding: 0.5rem;
                max-height: 60vh;
            }

            .d-flex.justify-content-end {
                justify-content: center !important;
                margin-bottom: 1rem;
            }
        }

        
    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        min-width: 250px;
        background: #333;
        color: white;
        padding: 10px 15px;
        border-radius: 4px;
        animation: fadeIn 0.3s;
        z-index: 1100;
        display: none;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }


        /* Transporteur section */
        .transporteur-item {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }

        /* Preview image */
        #previewImage {
            max-width: 100%;
            height: auto;
            max-height: 200px;
            object-fit: contain;
        }

        /* Statut sections */
        .status-section {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        /* Styles pour la nouvelle modal */
        .tracking-info {
            transition: all 0.3s ease;
            border: 1px solid #dee2e6;
        }

        .tracking-info:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            position: relative;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: transparent;
            border-bottom: 3px solid var(--primary-color);
        }

        .nav-tabs .nav-link:not(.active):hover {
            color: var(--primary-color);
            border-color: transparent;
        }

        .status-section {
            border-left: 4px solid var(--primary-color);
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }

        /* Améliorations responsive */
        @media (max-width: 768px) {
            .modal-body .row {
                flex-direction: column;
            }

            .col-md-4,
            .col-md-8 {
                width: 100%;
            }

            .nav-tabs .nav-link {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>


    <div id="sidebar">
        <div class="logo-container">
            <a href="index.php"><img src="OIG1.jpeg" alt="Logo SPIISTMOVE" class="logo img-fluid"></a>
        </div>
        <ul class="menu-items">
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Accueil</a></li>
            <li><a href="poster-colis.php"><i class="fas fa-box"></i> Poster colis</a></li>
            <li><a href="profil.php"><i class="fas fa-user"></i> Profil</a></li>
            <li><a href="liste-messagerie.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="devenir-transporteur.php"><i class="fas fa-truck"></i> Devenir transporteur</a></li>
            <li><a href="colis.php"><i class="fas fa-box-open"></i> Colis</a></li>
            <li><a href="suivi.php"><i class="fas fa-search-location"></i> Suivi</a></li>
            <li><a href="contact.php"><i class="fas fa-phone-alt"></i> Contact</a></li>
            <li><a href="auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>


    <div class="content">
        <div class="container py-3">
            <div class="d-flex justify-content-end mb-3">
                <a href="auth.php?logout=1" class="btn btn-danger btn-sm">
                    <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
                </a>
            </div>


            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form action="resultat.php" method="get" class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label for="colis_id" class="form-label fw-bold">
                                <i class="fa-solid fa-search"></i> Rechercher un transporteur :
                            </label>
                            <select name="colis_id" id="colis_id" class="form-select" required>
                                <option value="">-- Sélectionnez un colis --</option>
                                <?php foreach ($colis as $c): ?>
                                    <option value="<?= $c['id'] ?>">
                                        <?= htmlspecialchars($c['nom_colis']) ?> (<?= htmlspecialchars($c['pays']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-truck"></i> Rechercher
                            </button>
                        </div>
                    </form>
                </div>
            </div>


            <h2 class="text-primary text-center mb-4"><i class="fa-solid fa-box"></i> Mes colis postés</h2>
            <div class="table-responsive mb-5">
                <table class="table table-bordered align-middle shadow-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th class="mobile-hide">Image</th>
                            <th class="mobile-hide">Type</th>
                            <th>Poids</th>
                            <th>Destination</th>
                            <th class="mobile-hide">Date limite</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($colis as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['nom_colis']) ?></td>
                                <td class="mobile-hide">
                                    <?php if ($c['image_colis']): ?>
                                        <img src="<?= htmlspecialchars($c['image_colis']) ?>" class="rounded">
                                    <?php else: ?>
                                        <img src="assets/default-colis.png" class="rounded">
                                    <?php endif; ?>
                                </td>
                                <td class="mobile-hide"><?= htmlspecialchars($c['type_produit']) ?></td>
                                <td><?= htmlspecialchars($c['poids']) ?> kg</td>
                                <td><?= htmlspecialchars($c['ville']) ?>, <?= htmlspecialchars($c['pays']) ?></td>
                                <td class="mobile-hide"><?= htmlspecialchars($c['date_limite']) ?></td>
                                <td>
                                    <?php if ($c['statut'] == 'en_attente'): ?>
                                        <span class="badge bg-warning text-dark">En attente</span>
                                    <?php elseif ($c['statut'] == 'approuve'): ?>
                                        <span class="badge bg-success">Approuvé</span>
                                    <?php elseif ($c['statut'] == 'refuse'): ?>
                                        <span class="badge bg-danger">Refusé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsColis<?= $c['id'] ?>">
                                            <i class="fas fa-info-circle"></i>
                                            <span class="d-none d-md-inline">Détails</span>
                                        </button>
                                        <a href="reservation-colis.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fa-solid fa-eye"></i>
                                            <span class="d-none d-md-inline">Réservations</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <!-- Modal de détails du colis avec formulaire d'édition -->
                            <div class="modal fade" id="detailsColis<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-light">
                                            <h5 class="modal-title">Détails du colis: <?= htmlspecialchars($c['nom_colis']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="update_colis.php" method="post" enctype="multipart/form-data">
                                            <?= csrf_field() ?>
                                            <div class="modal-body">
                                                <input type="hidden" name="colis_id" value="<?= $c['id'] ?>">

                                                <div class="row">
                                                    <!-- Colonne de gauche - Image et suivi -->
                                                    <div class="col-md-4 mb-3 mb-md-0">
                                                        <div class="card border-0 shadow-sm h-100">
                                                            <div class="card-body text-center">
                                                                <?php if ($c['image_colis']): ?>
                                                                    <img src="<?= htmlspecialchars($c['image_colis']) ?>" class="img-fluid rounded mb-3" id="previewImage<?= $c['id'] ?>" style="max-height: 200px;">
                                                                <?php else: ?>
                                                                    <img src="assets/default-colis.png" class="img-fluid rounded mb-3" id="previewImage<?= $c['id'] ?>" style="max-height: 200px;">
                                                                <?php endif; ?>

                                                                <!-- Numéro de suivi amélioré -->
                                                                <div class="tracking-info bg-light p-3 rounded border">
                                                                    <h6 class="text-muted mb-2"><i class="fas fa-barcode"></i> Numéro de suivi</h6>
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <code class="text-primary fs-6" id="numeroSuivi<?= $c['id'] ?>"><?= htmlspecialchars($c['numero_suivi']) ?></code>
                                                                        <button type="button" class="btn btn-sm btn-outline-primary copy-btn"
                                                                            data-clipboard-text="<?= htmlspecialchars($c['numero_suivi']) ?>"
                                                                            data-bs-toggle="tooltip" title="Copier dans le presse-papier">
                                                                            <i class="fas fa-copy"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>

                                                                <!-- Upload image -->
                                                                <div class="mt-3">
                                                                    <label for="image_colis<?= $c['id'] ?>" class="form-label small text-muted">Changer l'image</label>
                                                                    <input type="file" class="form-control form-control-sm" id="image_colis<?= $c['id'] ?>" name="image_colis" accept="image/*">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Colonne de droite - Formulaire -->
                                                    <div class="col-md-8">
                                                        <div class="card border-0 shadow-sm h-100">
                                                            <div class="card-body">
                                                                <!-- Barre d'onglets -->
                                                                <ul class="nav nav-tabs mb-4" id="colisTabs<?= $c['id'] ?>" role="tablist">
                                                                    <li class="nav-item" role="presentation">
                                                                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details-content<?= $c['id'] ?>" type="button">
                                                                            <i class="fas fa-info-circle me-1"></i> Détails
                                                                        </button>
                                                                    </li>
                                                                    <li class="nav-item" role="presentation">
                                                                        <button class="nav-link" id="status-tab" data-bs-toggle="tab" data-bs-target="#status-content<?= $c['id'] ?>" type="button">
                                                                            <i class="fas fa-truck me-1"></i> Statut
                                                                        </button>
                                                                    </li>
                                                                    <?php if (!empty($c['reservations'])): ?>
                                                                        <li class="nav-item" role="presentation">
                                                                            <button class="nav-link" id="transporteurs-tab" data-bs-toggle="tab" data-bs-target="#transporteurs-content<?= $c['id'] ?>" type="button">
                                                                                <i class="fas fa-users me-1"></i> Transporteurs
                                                                            </button>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                </ul>

                                                                <!-- Contenu des onglets -->
                                                                <div class="tab-content" id="colisTabsContent<?= $c['id'] ?>">
                                                                    <!-- Onglet Détails -->
                                                                    <div class="tab-pane fade show active" id="details-content<?= $c['id'] ?>" role="tabpanel">
                                                                        <div class="row g-3">
                                                                            <div class="col-md-6">
                                                                                <label for="nom_colis<?= $c['id'] ?>" class="form-label">Nom du colis*</label>
                                                                                <input type="text" class="form-control" id="nom_colis<?= $c['id'] ?>" name="nom_colis" value="<?= htmlspecialchars($c['nom_colis']) ?>" required>
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <label for="type_produit<?= $c['id'] ?>" class="form-label">Type de produit*</label>
                                                                                <select class="form-select" id="type_produit<?= $c['id'] ?>" name="type_produit" required>
                                                                                    <option value="documents" <?= $c['type_produit'] == 'documents' ? 'selected' : '' ?>>Documents</option>
                                                                                    <option value="vetements" <?= $c['type_produit'] == 'vetements' ? 'selected' : '' ?>>Vêtements</option>
                                                                                    <option value="electronique" <?= $c['type_produit'] == 'electronique' ? 'selected' : '' ?>>Électronique</option>
                                                                                    <option value="alimentaire" <?= $c['type_produit'] == 'alimentaire' ? 'selected' : '' ?>>Alimentaire</option>
                                                                                    <option value="autre" <?= $c['type_produit'] == 'autre' ? 'selected' : '' ?>>Autre</option>
                                                                                </select>
                                                                            </div>

                                                                            <div class="col-md-4">
                                                                                <label for="nombre_produits<?= $c['id'] ?>" class="form-label">Quantité*</label>
                                                                                <input type="number" class="form-control" id="nombre_produits<?= $c['id'] ?>" name="nombre_produits" value="<?= htmlspecialchars($c['nombre_produits']) ?>" required>
                                                                            </div>
                                                                            <div class="col-md-4">
                                                                                <label for="poids<?= $c['id'] ?>" class="form-label">Poids (kg)*</label>
                                                                                <input type="number" step="0.01" class="form-control" id="poids<?= $c['id'] ?>" name="poids" value="<?= htmlspecialchars($c['poids']) ?>" required>
                                                                            </div>
                                                                            <div class="col-md-4">
                                                                                <label for="prix_estime<?= $c['id'] ?>" class="form-label">Prix estimé (F CFA)</label>
                                                                                <input type="number" step="0.01" class="form-control" id="prix_estime<?= $c['id'] ?>" name="prix_estime" value="<?= htmlspecialchars($c['prix_estime']) ?>" readonly>
                                                                            </div>

                                                                            <div class="col-12">
                                                                                <label for="dimensions<?= $c['id'] ?>" class="form-label">Dimensions (L × l × H en cm)*</label>
                                                                                <input type="text" class="form-control" id="dimensions<?= $c['id'] ?>" name="dimensions" value="<?= htmlspecialchars($c['dimensions']) ?>" placeholder="Ex: 30 20 10" required>
                                                                            </div>

                                                                            <div class="col-md-6">
                                                                                <label for="pays<?= $c['id'] ?>" class="form-label">Pays de destination*</label>
                                                                                <input type="text" class="form-control" id="pays<?= $c['id'] ?>" name="pays" value="<?= htmlspecialchars($c['pays']) ?>" required>
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <label for="ville<?= $c['id'] ?>" class="form-label">Ville de destination*</label>
                                                                                <input type="text" class="form-control" id="ville<?= $c['id'] ?>" name="ville" value="<?= htmlspecialchars($c['ville']) ?>" required>
                                                                            </div>

                                                                            <div class="col-md-6">
                                                                                <label for="date_limite<?= $c['id'] ?>" class="form-label">Date limite*</label>
                                                                                <input type="date" class="form-control" id="date_limite<?= $c['id'] ?>" name="date_limite" value="<?= htmlspecialchars($c['date_limite']) ?>" required>
                                                                            </div>

                                                                            <div class="col-12">
                                                                                <label for="adresse_depart<?= $c['id'] ?>" class="form-label">Adresse de départ*</label>
                                                                                <input type="text" class="form-control" id="adresse_depart<?= $c['id'] ?>" name="adresse_depart" value="<?= htmlspecialchars($c['adresse_depart']) ?>" required>
                                                                            </div>
                                                                            <div class="col-12">
                                                                                <label for="adresse_destination<?= $c['id'] ?>" class="form-label">Adresse de destination*</label>
                                                                                <input type="text" class="form-control" id="adresse_destination<?= $c['id'] ?>" name="adresse_destination" value="<?= htmlspecialchars($c['adresse_destination']) ?>" required>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Onglet Statut -->
                                                                    <!-- Dans la partie "Onglet Statut" de la modale (dashboard.php) -->
                                                                    <!-- Onglet Statut -->
                                                                    <div class="tab-pane fade" id="status-content<?= $c['id'] ?>" role="tabpanel">
                                                                        <div class="status-section mb-4">
                                                                            <h5 class="d-flex align-items-center mb-3">
                                                                                <i class="fas fa-info-circle text-primary me-2"></i>
                                                                                <span>Statut du colis</span>
                                                                            </h5>
                                                                            <div class="d-flex align-items-center p-3 bg-light rounded border-start border-primary border-4">
                                                                                <?php if ($c['statut'] == 'en_attente'): ?>
                                                                                    <span class="badge bg-warning text-dark me-3">En attente</span>
                                                                                    <p class="mb-0">En attente d'approbation par l'administrateur</p>
                                                                                <?php elseif ($c['statut'] == 'approuve'): ?>
                                                                                    <span class="badge bg-success me-3">Approuvé</span>
                                                                                    <p class="mb-0">Votre colis a été approuvé et est visible par les transporteurs</p>
                                                                                <?php elseif ($c['statut'] == 'refuse'): ?>
                                                                                    <span class="badge bg-danger me-3">Refusé</span>
                                                                                    <p class="mb-0">Votre colis a été refusé pour les raisons suivantes: [raison]</p>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>

                                                                        <?php if ($c['statut'] == 'approuve'): ?>
                                                                            <div class="status-section">
                                                                                <h5 class="d-flex align-items-center mb-3">
                                                                                    <i class="fas fa-truck text-primary me-2"></i>
                                                                                    <span>Statut de livraison</span>
                                                                                </h5>
                                                                                <div class="d-flex align-items-center p-3 bg-light rounded border-start border-primary border-4">
                                                                                    <?php
                                                                                    // Vérifier si l'admin a confirmé la livraison
                                                                                    $stmt = $pdo->prepare("SELECT confirme_par_admin FROM suivi_colis WHERE colis_id = ? AND statut = 'Livré' ORDER BY date_etape DESC LIMIT 1");
                                                                                    $stmt->execute([$c['id']]);
                                                                                    $confirmation_admin = $stmt->fetchColumn();

                                                                                    if (empty($c['statut_livraison']) || $c['statut_livraison'] == 'En attente'):
                                                                                        if ($confirmation_admin === false):
                                                                                    ?>
                                                                                            <span class="badge bg-secondary me-3">En attente</span>
                                                                                            <p class="mb-0">En attente de confirmation par l'administrateur</p>
                                                                                        <?php else: ?>
                                                                                            <span class="badge bg-secondary me-3">En attente</span>
                                                                                            <p class="mb-0">En attente de prise en charge par un transporteur</p>
                                                                                        <?php endif; ?>
                                                                                        <?php elseif ($c['statut_livraison'] == 'En cours'):
                                                                                        if ($confirmation_admin === false):
                                                                                        ?>
                                                                                            <span class="badge bg-warning me-3">En attente de confirmation</span>
                                                                                            <p class="mb-0">Le transporteur a marqué le colis comme "En cours", en attente de confirmation par l'admin</p>
                                                                                        <?php else: ?>
                                                                                            <span class="badge bg-info me-3">En cours</span>
                                                                                            <p class="mb-0">Votre colis est en cours de livraison</p>
                                                                                        <?php endif; ?>
                                                                                        <?php elseif ($c['statut_livraison'] == 'Livré'):
                                                                                        if ($confirmation_admin === false):
                                                                                        ?>
                                                                                            <span class="badge bg-warning me-3">En attente de confirmation</span>
                                                                                            <p class="mb-0">Le transporteur a marqué le colis comme "Livré", en attente de confirmation par l'admin</p>
                                                                                        <?php else: ?>
                                                                                            <span class="badge bg-success me-3">Livré</span>
                                                                                            <p class="mb-0">Votre colis a été livré avec succès</p>
                                                                                        <?php endif; ?>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>

                                                                    <!-- Onglet Transporteurs -->
                                                                    <?php if (!empty($c['reservations'])): ?>
                                                                        <div class="tab-pane fade" id="transporteurs-content<?= $c['id'] ?>" role="tabpanel">
                                                                            <?php foreach ($c['reservations'] as $reservation): ?>
                                                                                <?php
                                                                                // Récupérer les infos supplémentaires du transporteur
                                                                                $stmt = $pdo->prepare("SELECT AVG(note) as note_moyenne, COUNT(*) as nb_avis FROM avis WHERE transporteur_id = ?");
                                                                                $stmt->execute([$reservation['transporteur_id']]);
                                                                                $stats_transporteur = $stmt->fetch(PDO::FETCH_ASSOC);

                                                                                // Récupérer les 3 derniers avis
                                                                                $stmt = $pdo->prepare("SELECT a.*, u.nom, u.prenom FROM avis a JOIN users u ON a.user_id = u.id WHERE a.transporteur_id = ? AND a.statut = 'approuve' ORDER BY a.date_avis DESC LIMIT 3");
                                                                                $stmt->execute([$reservation['transporteur_id']]);
                                                                                $avis_transporteur = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                                                                // Récupérer l'historique des colis transportés
                                                                                $stmt = $pdo->prepare("SELECT COUNT(*) as nb_colis FROM reservations r JOIN colis c ON r.colis_id = c.id WHERE r.voyage_id IN (SELECT id FROM voyages WHERE user_id = ?) AND r.statut = 'accepte'");
                                                                                $stmt->execute([$reservation['transporteur_id']]);
                                                                                $historique_colis = $stmt->fetch(PDO::FETCH_ASSOC);
                                                                                ?>
                                                                                <div class="card mb-3">
                                                                                    <div class="card-body">
                                                                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                                                                            <div>
                                                                                                <h5 class="card-title mb-1"><?= htmlspecialchars($reservation['prenom'] . ' ' . $reservation['nom']) ?></h5>
                                                                                                <small class="text-muted d-block"><?= htmlspecialchars($reservation['email']) ?></small>
                                                                                                <small class="text-muted">Tél: <?= htmlspecialchars($reservation['telephone']) ?></small>
                                                                                            </div>
                                                                                            <div class="d-flex gap-2">
                                                                                                <a href="messagerie.php?destinataire_id=<?= $reservation['transporteur_id'] ?>&colis_id=<?= $c['id'] ?>"
                                                                                                    class="btn btn-sm btn-primary">
                                                                                                    <i class="fas fa-envelope"></i> Contacter
                                                                                                </a>
                                                                                                <a href="profil-transporteur.php?id=<?= $reservation['transporteur_id'] ?>"
                                                                                                    class="btn btn-sm btn-info">
                                                                                                    <i class="fas fa-user"></i> Profil
                                                                                                </a>
                                                                                            </div>
                                                                                        </div>

                                                                                        <!-- Statistiques du transporteur -->
                                                                                        <div class="row mb-3">
                                                                                            <div class="col-md-4">
                                                                                                <div class="d-flex align-items-center">
                                                                                                    <div class="me-3">
                                                                                                        <span class="display-6 fw-bold"><?= number_format($stats_transporteur['note_moyenne'] ?? 0, 1) ?></span>
                                                                                                        <span class="text-muted">/5</span>
                                                                                                    </div>
                                                                                                    <div>
                                                                                                        <div class="text-warning">
                                                                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                                                                <i class="fas fa-star<?= ($i <= round($stats_transporteur['note_moyenne'] ?? 0)) ? '' : '-empty' ?>"></i>
                                                                                                            <?php endfor; ?>
                                                                                                        </div>
                                                                                                        <small class="text-muted"><?= $stats_transporteur['nb_avis'] ?? 0 ?> avis</small>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div class="col-md-4">
                                                                                                <div class="d-flex align-items-center h-100">
                                                                                                    <i class="fas fa-box-open fa-2x text-primary me-3"></i>
                                                                                                    <div>
                                                                                                        <div class="fw-bold"><?= $historique_colis['nb_colis'] ?? 0 ?></div>
                                                                                                        <small class="text-muted">Colis transportés</small>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div class="col-md-4">
                                                                                                <div class="d-flex align-items-center h-100">
                                                                                                    <i class="fas fa-check-circle fa-2x text-success me-3"></i>
                                                                                                    <div>
                                                                                                        <div class="fw-bold">100%</div>
                                                                                                        <small class="text-muted">Livraisons réussies</small>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>

                                                                                        <!-- Avis récents -->
                                                                                        <?php if (!empty($avis_transporteur)): ?>
                                                                                            <h6 class="mt-3 mb-2">Avis récents :</h6>
                                                                                            <div class="border-top pt-2">
                                                                                                <?php foreach ($avis_transporteur as $avis): ?>
                                                                                                    <div class="mb-3 pb-2 border-bottom">
                                                                                                        <div class="d-flex justify-content-between">
                                                                                                            <div class="fw-bold"><?= htmlspecialchars($avis['prenom'] . ' ' . $avis['nom']) ?></div>
                                                                                                            <div class="text-warning">
                                                                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                                                                    <i class="fas fa-star<?= ($i <= $avis['note']) ? '' : '-empty' ?>"></i>
                                                                                                                <?php endfor; ?>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                        <div class="small text-muted"><?= date('d/m/Y', strtotime($avis['date_avis'])) ?></div>
                                                                                                        <div class="mt-1"><?= htmlspecialchars($avis['commentaire']) ?></div>
                                                                                                    </div>
                                                                                                <?php endforeach; ?>
                                                                                                <a href="profil-transporteur.php?id=<?= $reservation['transporteur_id'] ?>#avis" class="btn btn-sm btn-outline-primary">
                                                                                                    Voir tous les avis
                                                                                                </a>
                                                                                            </div>
                                                                                        <?php else: ?>
                                                                                            <div class="alert alert-info mb-0">
                                                                                                Ce transporteur n'a pas encore reçu d'avis.
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>


            <h2 class="text-primary text-center mb-4"><i class="fa-solid fa-truck"></i> Mes voyages proposés</h2>
            <div class="table-responsive">
                <table class="table table-bordered align-middle shadow-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Départ</th>
                            <th>Destination</th>
                            <th class="mobile-hide">Date</th>
                            <th class="mobile-hide">Heure</th>
                            <th>Poids max</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($voyages as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['pays_depart']) ?></td>
                                <td><?= htmlspecialchars($v['pays_destination']) ?></td>
                                <td class="mobile-hide"><?= htmlspecialchars($v['date_depart']) ?></td>
                                <td class="mobile-hide"><?= htmlspecialchars($v['heure_depart']) ?></td>
                                <td><?= htmlspecialchars($v['poids_max']) ?> kg</td>
                                <td>
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsVoyage<?= $v['id'] ?>">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="d-none d-md-inline">Détails</span>
                                    </button>
                                </td>
                            </tr>


                            <div class="modal fade" id="detailsVoyage<?= $v['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Détails du voyage</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item"><strong>De:</strong> <?= htmlspecialchars($v['pays_depart']) ?></li>
                                                <li class="list-group-item"><strong>À:</strong> <?= htmlspecialchars($v['pays_destination']) ?></li>
                                                <li class="list-group-item"><strong>Date:</strong> <?= htmlspecialchars($v['date_depart']) ?></li>
                                                <li class="list-group-item"><strong>Heure:</strong> <?= htmlspecialchars($v['heure_depart']) ?></li>
                                                <li class="list-group-item"><strong>Poids max:</strong> <?= htmlspecialchars($v['poids_max']) ?> kg</li>
                                                <li class="list-group-item"><strong>Contact:</strong> <?= htmlspecialchars($v['telephone']) ?></li>
                                            </ul>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // Réservations reçues sur MES voyages — lu en base, plus de cookies client falsifiables
            $stmt = $pdo->prepare("SELECT r.*, c.nom_colis, c.pays, c.ville, c.poids, c.image_colis
                FROM reservations r
                JOIN voyages v ON v.id = r.voyage_id
                JOIN colis c ON c.id = r.colis_id
                WHERE v.user_id = ?
                ORDER BY r.date_reservation DESC");
            $stmt->execute([(int) $_SESSION['user_id']]);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if ($reservations): ?>

                <h2 class="text-success text-center mb-4 mt-5"><i class="fa-solid fa-handshake"></i> Mes réservations</h2>
                <div class="table-responsive mb-5">
                    <table class="table table-bordered align-middle shadow-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Colis</th>
                                <th class="mobile-hide">Image</th>
                                <th>Poids</th>
                                <th>Destination</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($reservations as $r):
                                $stmt = $pdo->prepare("SELECT statut FROM suivi_colis WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1");
                                $stmt->execute([$r['colis_id']]);
                                $last_status = $stmt->fetch(PDO::FETCH_ASSOC);
                                $statut_suivi = $last_status ? $last_status['statut'] : 'En attente';
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['nom_colis']) ?></td>
                                    <td class="mobile-hide">
                                        <?php if ($r['image_colis']): ?>
                                            <img src="<?= htmlspecialchars($r['image_colis']) ?>" class="rounded">
                                        <?php else: ?>
                                            <img src="assets/default-colis.png" class="rounded">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($r['poids']) ?> kg</td>
                                    <td><?= htmlspecialchars($r['ville']) ?>, <?= htmlspecialchars($r['pays']) ?></td>
                                    <td>
                                        <?php if ($r['statut'] == 'en_attente'): ?>
                                            <span class="badge bg-warning text-dark">En attente</span>
                                        <?php elseif ($r['statut'] == 'accepte'): ?>
                                            <span class="badge bg-success">Accepté</span>
                                        <?php elseif ($r['statut'] == 'refuse'): ?>
                                            <span class="badge bg-danger">Refusé</span>
                                        <?php endif; ?>
                                    </td>
                                  <?php if ($statut_suivi != 'Livré'): ?>
<td>
    <form method="post" action="update_suivi_colis.php" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="colis_id" value="<?= $r['colis_id'] ?>">
        <select name="statut" class="form-select form-select-sm d-inline-block" style="width: auto;">
            <option value="En attente" <?= $statut_suivi == 'En attente' ? 'selected' : '' ?>>En attente</option>
            <option value="En cours" <?= $statut_suivi == 'En cours' ? 'selected' : '' ?>>En cours</option>
            <option value="Livré" <?= $statut_suivi == 'Livré' ? 'selected' : '' ?>>Livré</option>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-success">
            <i class="fas fa-sync-alt"></i>
        </button>
    </form>
</td>
<?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <h2 class="text-primary text-center mb-4"><i class="fas fa-money-bill-wave"></i> Mes paiements</h2>

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3><?= number_format($stats_paiements['montant_total'] ?? 0, 2) ?> F CFA</h3>
                            <p class="mb-0">Total payé</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?= $stats_paiements['payes'] ?? 0 ?></h3>
                            <p class="mb-0">Paiements réussis</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h3><?= $stats_paiements['en_attente'] ?? 0 ?></h3>
                            <p class="mb-0">En attente</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive mb-5">
                <table class="table table-bordered align-middle shadow-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Référence</th>
                            <th>Colis</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Opérateur</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($paiements)): ?>
                            <?php foreach ($paiements as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['reference']) ?></td>
                                    <td>
                                        <?= !empty($p['nom_colis']) ? htmlspecialchars($p['nom_colis']) : 'N/A' ?>
                                        <?php if (!empty($p['nom_colis'])): ?>
                                            <small class="text-muted d-block">ID: <?= $p['colis_id'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($p['montant'], 2) ?> €</td>
                                    <td><?= htmlspecialchars($p['methode_paiement']) ?></td>
                                    <td><?= htmlspecialchars($p['operateur']) ?></td>
                                    <td><?= htmlspecialchars($p['date_creation']) ?></td>
                                    <td>
                                        <?php if ($p['statut'] == 'paye'): ?>
                                            <span class="badge bg-success">Payé</span>
                                        <?php elseif ($p['statut'] == 'en_attente'): ?>
                                            <span class="badge bg-warning text-dark">En attente</span>
                                        <?php elseif ($p['statut'] == 'echec'): ?>
                                            <span class="badge bg-danger">Échec</span>
                                        <?php elseif ($p['statut'] == 'annule'): ?>
                                            <span class="badge bg-secondary">Annulé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsPaiement<?= $p['id'] ?>">
                                            <i class="fas fa-info-circle"></i>
                                            <span class="d-none d-md-inline">Détails</span>
                                        </button>
                                        <?php if ($p['statut'] == 'en_attente'): ?>
                                            <a href="payer.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-money-bill-wave"></i>
                                                <span class="d-none d-md-inline">Payer</span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Modal détails paiement -->
                                <div class="modal fade" id="detailsPaiement<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Détails du paiement #<?= $p['reference'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item">
                                                        <strong>Référence:</strong> <?= htmlspecialchars($p['reference']) ?>
                                                    </li>
                                                    <li class="list-group-item">
                                                        <strong>Montant:</strong> <?= number_format($p['montant'], 2) ?> F cfa
                                                    </li>
                                                    <li class="list-group-item">
                                                        <strong>Méthode:</strong> <?= htmlspecialchars($p['methode_paiement']) ?>
                                                    </li>
                                                    <li class="list-group-item">
                                                        <strong>Opérateur:</strong> <?= htmlspecialchars($p['operateur']) ?>
                                                    </li>
                                                    <li class="list-group-item">
                                                        <strong>Numéro transaction:</strong> <?= htmlspecialchars($p['numero_transaction'] ?? 'N/A') ?>
                                                    </li>
                                                    <li class="list-group-item">
                                                        <strong>Date création:</strong> <?= htmlspecialchars($p['date_creation']) ?>
                                                    </li>
                                                    <li class="list-group-item">
                                                        <strong>Statut:</strong>
                                                        <?php if ($p['statut'] == 'paye'): ?>
                                                            <span class="badge bg-success">Payé</span>
                                                        <?php elseif ($p['statut'] == 'en_attente'): ?>
                                                            <span class="badge bg-warning text-dark">En attente</span>
                                                        <?php elseif ($p['statut'] == 'echec'): ?>
                                                            <span class="badge bg-danger">Échec</span>
                                                        <?php elseif ($p['statut'] == 'annule'): ?>
                                                            <span class="badge bg-secondary">Annulé</span>
                                                        <?php endif; ?>
                                                    </li>
                                                    <?php if (!empty($p['details_paiement'])): ?>
                                                        <li class="list-group-item">
                                                            <strong>Détails:</strong>
                                                            <div class="mt-2 p-2 bg-light rounded">
                                                                <?php
                                                                $details = json_decode($p['details_paiement'], true);
                                                                if (json_last_error() === JSON_ERROR_NONE && is_array($details)) {
                                                                    // Affichage formaté pour du JSON
                                                                    echo '<ul class="list-unstyled">';
                                                                    foreach ($details as $key => $value) {
                                                                        $label = ucfirst(str_replace('_', ' ', $key));
                                                                        echo "<li><strong>{$label}:</strong> " . htmlspecialchars($value) . "</li>";
                                                                    }
                                                                    echo '</ul>';
                                                                } else {
                                                                    // Affichage normal si ce n'est pas du JSON
                                                                    echo nl2br(htmlspecialchars($p['details_paiement']));
                                                                }
                                                                ?>
                                                            </div>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                <?php if ($p['statut'] == 'en_attente'): ?>
                                                    <a href="payer.php?id=<?= $p['id'] ?>" class="btn btn-success">
                                                        <i class="fas fa-money-bill-wave"></i> Payer maintenant
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Aucun paiement enregistré</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>


            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3><?= number_format($solde, 2) ?> F CFA</h3>
                    <p class="mb-0">Votre solde</p>
                </div>
            </div>


           <?php if (isset($_SESSION['success'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function () { showToast(<?= json_encode($_SESSION['success']) ?>, "success"); });
    </script>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function () { showToast(<?= json_encode($_SESSION['error']) ?>, "error"); });
    </script>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
        </div>
    </div>


    <nav class="mobile-bottom-menu">
        <ul>
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Accueil</a></li>
            <li><a href="colis.php"><i class="fas fa-box"></i> Colis</a></li>
            <li><a href="liste-messagerie.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="profil.php"><i class="fas fa-user"></i> Profil</a></li>
        </ul>
    </nav>


    <div id="toast" class="toast"></div>

    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Menu mobile
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Fermer le menu quand on clique à l'extérieur
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('mobileMenuToggle');

            if (window.innerWidth <= 992 &&
                !sidebar.contains(event.target) &&
                event.target !== toggleBtn &&
                !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Gestion de la copie du numéro de suivi
        document.addEventListener('click', function(e) {
            if (e.target.closest('.copy-btn')) {
                const btn = e.target.closest('.copy-btn');
                const textToCopy = btn.getAttribute('data-clipboard-text');
                const tooltip = bootstrap.Tooltip.getInstance(btn);
                const originalTitle = btn.getAttribute('data-bs-original-title');

                navigator.clipboard.writeText(textToCopy).then(function() {
                    // Mettre à jour le tooltip
                    btn.setAttribute('data-bs-original-title', 'Copié !');
                    if (tooltip) tooltip.show();

                    // Réinitialiser après 2 secondes
                    setTimeout(() => {
                        btn.setAttribute('data-bs-original-title', originalTitle);
                        if (tooltip) tooltip.hide();
                    }, 2000);
                }).catch(function(err) {
                    showToast('Erreur lors de la copie', 'error');
                });
            }
        });

        // Prévisualisation de l'image
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const colisId = this.id.replace('image_colis', '');
                const previewImage = document.getElementById('previewImage' + colisId);
                const file = e.target.files[0];

                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
        });

        // Initialiser les tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });



        // Fonction pour afficher les notifications toast
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.display = 'block';
            toast.style.background = type === 'success' ? '#28a745' : '#dc3545';

            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
    </script>
    </script>
</body>

</html>
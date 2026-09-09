<?php
require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT * FROM colis WHERE id = ? AND statut = 'approuve'");
$stmt->execute([$id]);
$colis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colis) {
    die("Colis introuvable ou non approuvé.");
}

$est_transporteur = isset($_SESSION['role']) && $_SESSION['role'] === 'transporteur';
$voyage_id_transporteur = isset($_SESSION['voyage_id']) ? intval($_SESSION['voyage_id']) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails du colis</title>
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

       
        /* Sidebar styles */
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
        
        /* Mobile menu toggle */
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        /* Card styles */
        .detail-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .detail-card .card-header {
            border-bottom: none;
        }
        
        .list-group-item {
            padding: 12px 20px;
            border-left: none;
            border-right: none;
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
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .col-lg-7, .col-md-9 {
                width: 100%;
                max-width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .detail-card {
                border-radius: 0;
            }
        }
        
        /* Bottom mobile menu */
        .mobile-bottom-menu {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
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
        
        .mobile-bottom-menu a.active {
            color: var(--primary-color);
        }
        
        @media (max-width: 992px) {
            .mobile-bottom-menu {
                display: block;
            }
            
            /* Adjust content padding to account for bottom menu */
            .container.py-5 {
                padding-bottom: 70px !important;
            }
        }
    </style>
</head>
<body>
<?php if (!empty($_SESSION['error'])): ?>
    <div style="max-width:900px;margin:15px auto;padding:12px 18px;background:#f8d7da;color:#721c24;border:1px solid #f5c2c7;border-radius:8px;"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['success'])): ?>
    <div style="max-width:900px;margin:15px auto;padding:12px 18px;background:#d1e7dd;color:#0f5132;border:1px solid #badbcc;border-radius:8px;"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Menu -->
    <div id="sidebar">
        <div class="logo-container">
            <a href="index.php"><img src="OIG1.jpeg" alt="Logo SPIISTMOVE" class="logo"></a>
        </div>
        <ul class="menu-items">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Accueil</a></li>
            <li><a href="poster-colis.php"><i class="fas fa-box"></i> Poster colis</a></li>
            <li><a href="profil.php"><i class="fas fa-user"></i> Profil</a></li>
            <li><a href="liste-messagerie.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="devenir-transporteur.php"><i class="fas fa-truck"></i> Devenir transporteur</a></li>
            <li><a href="colis.php" class="active"><i class="fas fa-box-open"></i> Colis</a></li>
            <li><a href="suivi.php"><i class="fas fa-search-location"></i> Suivi</a></li>
            <li><a href="contact.php"><i class="fas fa-phone-alt"></i> Contact</a></li>
            <li><a href="auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-7 col-md-9">
                    <div class="card shadow-lg border-0 rounded-4 detail-card">
                        <div class="card-header bg-primary text-white text-center rounded-top-4">
                            <h2 class="mb-0"><i class="fa-solid fa-box"></i> Détail du colis</h2>
                        </div>
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <?php if ($colis['image_colis']): ?>
                                    <img src="<?= htmlspecialchars($colis['image_colis']) ?>" class="rounded-3 border" style="width:130px;height:130px;object-fit:cover;" alt="Colis">
                                <?php else: ?>
                                    <img src="assets/default-colis.png" class="rounded-3 border" style="width:130px;height:130px;object-fit:cover;" alt="Colis">
                                <?php endif; ?>
                            </div>
                            <ul class="list-group list-group-flush mb-4">
                                <li class="list-group-item"><i class="fa-solid fa-box text-primary me-2"></i> <strong>Nom :</strong> <?= htmlspecialchars($colis['nom_colis']) ?></li>
                                <li class="list-group-item"><i class="fa-solid fa-cube text-secondary me-2"></i> <strong>Type :</strong> <?= htmlspecialchars($colis['type_produit']) ?></li>
                                <li class="list-group-item"><i class="fa-solid fa-weight-hanging text-secondary me-2"></i> <strong>Poids :</strong> <?= htmlspecialchars($colis['poids']) ?> kg</li>
                                <li class="list-group-item"><i class="fa-solid fa-list-ol text-secondary me-2"></i> <strong>Nombre de produits :</strong> <?= htmlspecialchars($colis['nombre_produits']) ?></li>
                                <li class="list-group-item"><i class="fa-solid fa-ruler-combined text-secondary me-2"></i> <strong>Dimensions :</strong> <?= htmlspecialchars($colis['dimensions']) ?></li>
                                <li class="list-group-item"><i class="fa-solid fa-location-dot text-danger me-2"></i> <strong>Départ :</strong> <?= htmlspecialchars($colis['adresse_depart']) ?></li>
                                <li class="list-group-item"><i class="fa-solid fa-location-dot text-success me-2"></i> <strong>Destination :</strong> <?= htmlspecialchars($colis['pays']) ?>, <?= htmlspecialchars($colis['ville']) ?> (<?= htmlspecialchars($colis['adresse_destination']) ?>)</li>
                                <li class="list-group-item"><i class="fa-solid fa-calendar-days text-secondary me-2"></i> <strong>Date limite :</strong> <?= htmlspecialchars($colis['date_limite']) ?></li>
                                <li class="list-group-item"><i class="fa-solid fa-euro-sign text-warning me-2"></i> <strong>Prix estimé :</strong> <?= htmlspecialchars($colis['prix_estime']) ?> €</li>
                            </ul>
                            <?php if (!empty($_SESSION['is_transporteur']) && !empty($_SESSION['voyage_id'])): ?>
                                <form method="POST" action="reserver-colis.php" class="mb-3">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="colis_id" value="<?= (int) $colis['id'] ?>">
                                    <input type="hidden" name="voyage_id" value="<?= (int) $_SESSION['voyage_id'] ?>">
                                    <button type="submit" class="btn btn-success w-100 fw-bold">
                                        <i class="fa-solid fa-handshake"></i> Réserver ce colis
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="colis.php" class="btn btn-outline-primary w-100">
                                <i class="fa-solid fa-arrow-left"></i> Retour à la liste
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bottom Mobile Menu -->
    <nav class="mobile-bottom-menu">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Accueil</a></li>
            <li><a href="colis.php" class="active"><i class="fas fa-box"></i> Colis</a></li>
            <li><a href="liste-messagerie.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="profil.php"><i class="fas fa-user"></i> Profil</a></li>
        </ul>
    </nav>

    
    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle mobile menu
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Close menu when clicking outside on mobile
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
    </script>
</body>
</html>
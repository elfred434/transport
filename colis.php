<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

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


$stmt = $pdo->query("SELECT c.* FROM colis c 
                     LEFT JOIN suivi_colis sc ON c.id = sc.colis_id AND sc.confirme_par_admin = 1 AND sc.statut = 'Livré'
                     WHERE c.statut = 'approuve' AND sc.id IS NULL
                     ORDER BY c.date_post DESC");
$colis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des colis postés</title>
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
        .card {
            transition: transform 0.3s, box-shadow 0.3s;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        
        .card-img-top {
            height: 160px;
            object-fit: cover;
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
        }
        
        @media (max-width: 768px) {
            .col-md-4, .col-lg-3 {
                width: 50%;
            }
        }
        
        @media (max-width: 576px) {
            .col-12, .col-sm-6, .col-md-4, .col-lg-3 {
                width: 100%;
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
            .content {
                padding-bottom: 70px !important;
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
            <a href="index.php"><img src="OIG1.jpeg" alt="Logo SPIISTMOVE" class="logo"></a>
        </div>
        <ul class="menu-items">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Accueil</a></li>
            <li><a href="poster-colis.html"><i class="fas fa-box"></i> Poster colis</a></li>
            <li><a href="profil.php"><i class="fas fa-user"></i> Profil</a></li>
            <li><a href="liste-messagerie.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="devenir-transporteur.php"><i class="fas fa-truck"></i> Devenir transporteur</a></li>
            <li><a href="profil-transporteur.php"><i class="fas fa-user-tie"></i> Profil Transporteurs</a></li>
            <li><a href="transporteurs-statistiques.php"><i class="fas fa-users"></i> Transporteurs</a></li>
            <li><a href="colis.php" class="active"><i class="fas fa-box-open"></i> Colis</a></li>
            <li><a href="suivi.php"><i class="fas fa-search-location"></i> Suivi</a></li>
            <li><a href="contact.php"><i class="fas fa-phone-alt"></i> Contact</a></li>
            <li><a href="auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>
    
    
    <div class="content">
        <div class="container py-3">
            <h2 class="text-center text-primary mb-4"><i class="fa-solid fa-box"></i> Tous les colis postés</h2>
            <div class="row g-4">
                <?php foreach($colis as $c): ?>
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                        <div class="card shadow-sm h-100 border-0">
                            <?php if ($c['image_colis']): ?>
                                <img src="<?= htmlspecialchars($c['image_colis']) ?>" class="card-img-top" alt="Colis">
                            <?php else: ?>
                                <img src="assets/default-colis.png" class="card-img-top" alt="Colis">
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-primary fw-bold mb-2"><?= htmlspecialchars($c['nom_colis']) ?></h5>
                                <p class="card-text mb-1">
                                    <i class="fa-solid fa-location-dot text-danger"></i>
                                    <?= htmlspecialchars($c['pays']) ?>, <?= htmlspecialchars($c['ville']) ?>
                                </p>
                                <p class="card-text mb-1">
                                    <i class="fa-solid fa-cube text-secondary"></i>
                                    <?= htmlspecialchars($c['type_produit']) ?>
                                </p>
                                <p class="card-text mb-1">
                                    <i class="fa-solid fa-weight-hanging text-secondary"></i>
                                    <?= htmlspecialchars($c['poids']) ?> kg
                                </p>
                                <a href="colis-detail.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary mt-auto w-100">
                                    <i class="fa-solid fa-eye"></i> Voir détails
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($colis)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="fa-solid fa-circle-info"></i> Aucun colis disponible pour le moment.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    
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
        
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        
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
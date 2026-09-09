<?php
require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$numero_suivi = isset($_GET['numero_suivi']) ? $_GET['numero_suivi'] : '';
$etapes = [];
$etat_colis = '';

if ($numero_suivi) {
    $stmt = $pdo->prepare("SELECT id FROM colis WHERE numero_suivi = ?");
    $stmt->execute([$numero_suivi]);
    $colis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($colis) {
        $stmt = $pdo->prepare("SELECT * FROM suivi_colis WHERE colis_id = ? AND (confirme_par_admin = TRUE OR statut != 'Livré') ORDER BY date_etape ASC");
        $stmt->execute([$colis['id']]);
        $etapes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT statut FROM suivi_colis WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1");
        $stmt->execute([$colis['id']]);
        $last_etape = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last_etape) {
            $etat_colis = $last_etape['statut'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi du colis</title>
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
        
        /* Tracking card styles */
        .tracking-card {
            max-width: 500px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        
        .tracking-card .card-header {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            padding: 15px;
        }
        
        .tracking-step .fa {
            font-size: 22px;
        }
        
        .done {
            color: #27ae60;
        }
        
        .current {
            color: #f39c12;
        }
        
        .pending {
            color: #b2bec3;
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
                padding-bottom: 70px;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .tracking-card {
                margin: 20px;
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
        
        @media (max-width: 992px) {
            .mobile-bottom-menu {
                display: block;
            }
        }
    </style>
</head>
<body>
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
            <li><a href="colis.php"><i class="fas fa-box-open"></i> Colis</a></li>
            <li><a href="suivi.php" class="active"><i class="fas fa-search-location"></i> Suivi</a></li>
            <li><a href="contact.php"><i class="fas fa-phone-alt"></i> Contact</a></li>
            <li><a href="auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <div class="tracking-card card">
            <div class="card-header">
                <h2 class="mb-0"><i class="fa-solid fa-location-dot"></i> Suivi du colis</h2>
            </div>
            <div class="card-body">
                <form method="get" class="mb-4">
                    <label for="tracking-number" class="form-label fw-bold">Numéro de suivi</label>
                    <input type="text" id="tracking-number" name="numero_suivi" class="form-control mb-3" placeholder="Ex : COLIS..." required value="<?= htmlspecialchars($numero_suivi) ?>">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa-solid fa-magnifying-glass-location"></i> Suivre
                    </button>
                </form>
                <?php if ($etat_colis): ?>
                    <div class="mb-3 text-center">
                        <?php if ($etat_colis == 'En attente'): ?>
                            <span class="badge bg-warning text-dark fs-5"><i class="fa-solid fa-hourglass-half"></i> En attente</span>
                        <?php elseif ($etat_colis == 'En cours'): ?>
                            <span class="badge bg-primary fs-5"><i class="fa-solid fa-truck"></i> En cours</span>
                        <?php elseif ($etat_colis == 'Livré'): ?>
                            <span class="badge bg-success fs-5"><i class="fa-solid fa-circle-check"></i> Livré</span>
                            <div class="alert alert-success mt-2">
                                Ce colis a été livré et ne peut plus être modifié.
                            </div>
                        <?php else: ?>
                            <span class="badge bg-secondary fs-5"><?= htmlspecialchars($etat_colis) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <ul class="list-group">
                    <?php if ($numero_suivi && empty($etapes)): ?>
                        <li class="list-group-item pending text-center">
                            <i class="fa-solid fa-circle-xmark"></i> Aucun suivi trouvé pour ce numéro.
                        </li>
                    <?php endif; ?>
                    <?php foreach($etapes as $i => $etape): ?>
                        <li class="list-group-item d-flex align-items-center tracking-step <?= $i == count($etapes)-1 ? 'current' : 'done' ?>">
                            <i class="fa-solid fa-truck me-3"></i>
                            <span><?= htmlspecialchars($etape['statut']) ?></span>
                            <span class="ms-auto text-muted small"><?= htmlspecialchars($etape['date_etape']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Bottom Mobile Menu -->
    <nav class="mobile-bottom-menu">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Accueil</a></li>
            <li><a href="colis.php"><i class="fas fa-box"></i> Colis</a></li>
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
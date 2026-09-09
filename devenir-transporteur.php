<?php
require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email, telephone FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devenir transporteur</title>
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
        
        /* Form styles */
        .form-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        
        .form-container h1 {
            text-align: center;
            color: var(--secondary-color);
            margin-bottom: 30px;
        }
        
        .form-container label {
            display: block;
            margin-bottom: 6px;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .form-container input,
        .form-container select {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 18px;
            border: 1px solid #dce1e6;
            border-radius: 6px;
            background: #f9fafb;
            font-size: 15px;
            transition: border 0.2s;
        }
        
        .form-container input:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .form-container button {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .form-container button:hover {
            background: #206694;
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
            .form-container {
                margin: 20px;
                padding: 20px;
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
            <li><a href="devenir-transporteur.php" class="active"><i class="fas fa-truck"></i> Devenir transporteur</a></li>
            <li><a href="colis.php"><i class="fas fa-box-open"></i> Colis</a></li>
            <li><a href="suivi.php"><i class="fas fa-search-location"></i> Suivi</a></li>
            <li><a href="contact.php"><i class="fas fa-phone-alt"></i> Contact</a></li>
            <li><a href="auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <div class="form-container">
            <h1>Devenir transporteur</h1>
            <form action="traitement-transporteur.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <label for="numero_permis">Numéro de permis :</label>
                <input type="text" id="numero_permis" name="numero_permis" required>

                <label for="vehicule">Type de véhicule :</label>
                <input type="text" id="vehicule" name="vehicule" required>

                <label for="compagnie">Compagnie :</label>
                <input type="text" id="compagnie" name="compagnie">

                <label for="adresse">Adresse :</label>
                <input type="text" id="adresse" name="adresse" required>

                <label for="ville">Ville :</label>
                <input type="text" id="ville" name="ville" required>

                <label for="pays">Pays :</label>
                <input type="text" id="pays" name="pays" required>

                <label for="photo_vehicule">Photo du véhicule :</label>
                <input type="file" id="photo_vehicule" name="photo_vehicule" accept="image/*">

                <label for="pays_depart">Pays de départ :</label>
                <input type="text" id="pays_depart" name="pays_depart" required>

                <label for="pays_destination">Pays de destination :</label>
                <input type="text" id="pays_destination" name="pays_destination" required>

                <label for="date_depart">Date de départ :</label>
                <input type="date" id="date_depart" name="date_depart" required>

                <label for="heure_depart">Heure de départ :</label>
                <input type="time" id="heure_depart" name="heure_depart" required>

                <label for="poids_max">Poids maximum accepté (kg) :</label>
                <input type="number" id="poids_max" name="poids_max" min="1" required>

                <label for="email">Email :</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">

                <label for="telephone">Numéro de téléphone :</label>
                <input type="tel" id="telephone" name="telephone" required value="<?= htmlspecialchars($user['telephone']) ?>">

                <button type="submit">Poster mon voyage</button>
            </form>
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
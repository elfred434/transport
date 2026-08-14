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

$user_id = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $photo_profil = null;

    if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['photo_profil']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['photo_profil']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $photo_profil = 'uploads/profil_' . $user_id . '_' . time() . '.' . $ext;
            move_uploaded_file($tmp_name, $photo_profil);
        }
    }

    $sql = "UPDATE users SET nom = ?, prenom = ?, email = ?, telephone = ?";
    $params = [$nom, $prenom, $email, $telephone];
    if ($photo_profil) {
        $sql .= ", photo_profil = ?";
        $params[] = $photo_profil;
    }
    $sql .= " WHERE id = ?";
    $params[] = $user_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $msg = "Profil mis à jour avec succès.";
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier mon profil</title>
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
        .profile-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        
        .profile-container h2 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 24px;
        }
        
        .profile-container label {
            font-weight: bold;
            display: block;
            margin-top: 12px;
            color: var(--secondary-color);
        }
        
        .profile-container input {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #dce1e6;
            border-radius: 6px;
            background: #f9fafb;
            margin-bottom: 14px;
        }
        
        .profile-container button {
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
        
        .profile-container button:hover {
            background: #206694;
        }
        
        .avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin: 0 auto 18px auto;
            border: 2px solid var(--primary-color);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 18px;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .msg-success {
            color: #27ae60;
            font-weight: bold;
            margin-bottom: 18px;
            text-align: center;
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
            .profile-container {
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
            <li><a href="poster-colis.html"><i class="fas fa-box"></i> Poster colis</a></li>
            <li><a href="profil.php"><i class="fas fa-user"></i> Profil</a></li>
            <li><a href="liste-messagerie.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="devenir-transporteur.php"><i class="fas fa-truck"></i> Devenir transporteur</a></li>
            <li><a href="colis.php"><i class="fas fa-box-open"></i> Colis</a></li>
            <li><a href="suivi.php"><i class="fas fa-search-location"></i> Suivi</a></li>
            <li><a href="contact.php"><i class="fas fa-phone-alt"></i> Contact</a></li>
            <li><a href="auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <div class="profile-container">
            <a href="profil.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Retour au profil</a>
            <h2>Modifier mon profil</h2>
            <?php if ($msg): ?>
                <div class="msg-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php
                $default_img = "assets/default-avatar.png";
                $img = (!empty($user['photo_profil']) && file_exists($user['photo_profil'])) ? htmlspecialchars($user['photo_profil']) : $default_img;
            ?>
            <img src="<?= $img ?>" alt="Photo de profil" class="avatar">
            <form method="post" enctype="multipart/form-data">
                <label for="nom">Nom</label>
                <input type="text" id="nom" name="nom" required value="<?= htmlspecialchars($user['nom']) ?>">

                <label for="prenom">Prénom</label>
                <input type="text" id="prenom" name="prenom" required value="<?= htmlspecialchars($user['prenom']) ?>">

                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">

                <label for="telephone">Téléphone</label>
                <input type="tel" id="telephone" name="telephone" required value="<?= htmlspecialchars($user['telephone']) ?>">

                <label for="photo_profil">Photo de profil (laisser vide pour ne pas changer)</label>
                <input type="file" id="photo_profil" name="photo_profil" accept="image/*">

                <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications</button>
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
<?php
require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];


$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);


$colis = $pdo->prepare("SELECT * FROM colis WHERE user_id = ? ORDER BY date_post DESC");
$colis->execute([$user_id]);
$colis = $colis->fetchAll(PDO::FETCH_ASSOC);

$voyages = $pdo->prepare("SELECT * FROM voyages WHERE user_id = ? ORDER BY date_post DESC");
$voyages->execute([$user_id]);
$voyages = $voyages->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil</title>
    <style>
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --light-color: #ffffff;
            --dark-color: #2c3e50;
            --gray-light: #f8f9fa;
            --gray-medium: #e9ecef;
            --gray-dark: #6c757d;
            --border-radius: 6px;
            --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        html,
        body {
            height: 100%;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
        }

        
        .navbar {
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 0.8rem 1rem;
            box-shadow: var(--box-shadow);
            flex-shrink: 0;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            font-size: 1.3rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--light-color);
            gap: 0.5rem;
        }

        .navbar-toggler {
            background: none;
            border: none;
            color: var(--light-color);
            font-size: 1.5rem;
            cursor: pointer;
            display: none;
            padding: 0.5rem;
        }

        .navbar-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-nav {
            display: flex;
            list-style: none;
            gap: 0.5rem;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.6rem 1rem;
            text-decoration: none;
            color: var(--light-color);
            font-weight: 500;
            border-radius: var(--border-radius);
            transition: var(--transition);
            gap: 0.5rem;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .nav-link i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        .btn-deconnexion {
            background-color: var(--danger-color);
            color: var(--light-color) !important;
            border: none;
            padding: 0.6rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 0.5rem;
            text-decoration: none;
        }

        .btn-deconnexion:hover {
            background-color: #c82333;
        }

         
        .container {
            flex: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }

        .profile-header {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 40px;
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .profile-avatar {
            flex: 0 0 150px;
        }

        .profile-avatar img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .profile-info {
            flex: 1;
            min-width: 300px;
        }

        .profile-name {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 28px;
        }

        .profile-details {
            list-style: none;
            margin-bottom: 20px;
        }

        .profile-details li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-details li i {
            color: var(--primary-color);
            width: 20px;
            text-align: center;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border: 2px solid transparent;
            text-align: center;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-outline {
            background-color: transparent;
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .section-title {
            color: var(--primary-color);
            margin: 30px 0 15px;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow-x: auto;
            margin-bottom: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-warning {
            background-color: var(--warning-color);
            color: #000;
        }

        .badge-success {
            background-color: var(--secondary-color);
            color: white;
        }

        .badge-danger {
            background-color: var(--danger-color);
            color: white;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .navbar-toggler {
                display: block;
            }

            .navbar-menu {
                display: none;
                width: 100%;
                flex-direction: column;
                padding-top: 1rem;
            }

            .navbar-menu.active {
                display: flex;
            }

            .navbar-nav {
                flex-direction: column;
                width: 100%;
            }

            .nav-item {
                width: 100%;
            }

            .nav-link {
                padding: 0.8rem 1rem;
                justify-content: flex-start;
            }

            .btn-deconnexion {
                margin: 0.5rem 0 0 0;
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .profile-details li {
                justify-content: center;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .profile-avatar img {
                width: 120px;
                height: 120px;
            }

            .profile-name {
                font-size: 24px;
            }

            th,
            td {
                padding: 8px 10px;
                font-size: 14px;
            }
        }

        /* Optimisations responsive supplémentaires */
        @media (max-width: 992px) {
            .profile-header {
                padding: 20px;
            }

            .action-buttons {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .profile-info {
                min-width: 100%;
            }

            table {
                font-size: 14px;
            }

            th,
            td {
                padding: 8px 10px;
            }

            .section-title {
                font-size: 20px;
                margin: 20px 0 10px;
            }
        }

        @media (max-width: 576px) {
            .profile-avatar {
                flex: 0 0 120px;
            }

            .profile-avatar img {
                width: 120px;
                height: 120px;
            }

            .profile-name {
                font-size: 22px;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            /* Adaptation des tables pour mobile */
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .table-container {
                border-radius: 0;
                box-shadow: none;
            }
        }

        @media (max-width: 480px) {
            .profile-header {
                padding: 15px;
            }

            .profile-details li {
                flex-direction: column;
                text-align: center;
                gap: 5px;
            }

            .profile-details li i {
                margin-bottom: 5px;
            }

            /* Masquer certaines colonnes non essentielles */
            td:nth-child(2),
            th:nth-child(2),
            /* Image */
            td:nth-child(7),
            th:nth-child(7),
            /* Prix estimé */
            td:nth-child(6),
            th:nth-child(6)

            /* Date limite */
                {
                display: none;
            }
        }

        
        @media (max-width: 400px) {
            
            .table-container:first-of-type td:nth-child(4),
            .table-container:first-of-type th:nth-child(4)

            
                {
                display: none;
            }

            
            .table-container:last-of-type td:nth-child(5),
            .table-container:last-of-type th:nth-child(5),
            
            .table-container:last-of-type td:nth-child(6),
            .table-container:last-of-type th:nth-child(6),
            
            .table-container:last-of-type td:nth-child(7),
            .table-container:last-of-type th:nth-child(7)

            



                {
                display: none;
            }
        }
    </style>
    <!-- <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
</head>

<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <img src="OIG1.jpeg" alt="Logo" style="height:38px;border-radius:50%;margin-right:8px;">
                SPIISTMOVE
            </a>
            <button id="navbarToggler" class="navbar-toggler" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="navbar-menu" id="navbarMenu">
                <ul class="navbar-nav">
                    <li class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Accueil</a></li>
                    <li class="nav-item"><a href="profil.php" class="nav-link"><i class="fas fa-user"></i> Profil</a></li>
                    <li class="nav-item"><a href="liste-messagerie.php" class="nav-link"><i class="fas fa-envelope"></i> Messagerie</a></li>
                    <li class="nav-item"><a href="poster-colis.php" class="nav-link"><i class="fas fa-box"></i> Poster un colis</a></li>
                    <li class="nav-item"><a href="devenir-transporteur.php" class="nav-link"><i class="fas fa-truck"></i> Devenir transporteur</a></li>
                </ul>
                <a href="auth.php?logout=1" class="btn-deconnexion"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
    </nav>


    <div class="container">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php
                $default_img = "assets/default-avatar.png";
                $img = (!empty($user['photo_profil']) && file_exists($user['photo_profil'])) ? htmlspecialchars($user['photo_profil']) : $default_img;
                ?>
                <img src="<?= $img ?>" alt="Photo de profil">
            </div>
            <div class="profile-info">
                <h1 class="profile-name">
                    <i class="fa-solid fa-user"></i> <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                </h1>
                <ul class="profile-details">
                    <li><i class="fa-solid fa-envelope"></i> <strong>Email :</strong> <?= htmlspecialchars($user['email']) ?></li>
                    <li><i class="fa-solid fa-phone"></i> <strong>Téléphone :</strong> <?= htmlspecialchars($user['telephone']) ?></li>
                    <li><i class="fa-solid fa-calendar"></i> <strong>Date d'inscription :</strong> <?= htmlspecialchars($user['date_inscription']) ?></li>
                </ul>
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fa-solid fa-gauge"></i> Tableau de bord
                    </a>
                    <a href="colis.php" class="btn btn-outline">
                        <i class="fa-solid fa-box"></i> Tous les colis
                    </a>
                    <a href="poster-colis.php" class="btn btn-outline">
                        <i class="fa-solid fa-plus"></i> Poster un colis
                    </a>
                    <a href="devenir-transporteur.php" class="btn btn-outline">
                        <i class="fa-solid fa-truck"></i> Devenir transporteur
                    </a>
                    <a href="liste-messagerie.php" class="btn btn-outline">
                        <i class="fa-solid fa-envelope"></i> Messagerie
                    </a>
                    <a href="suivi.php" class="btn btn-outline">
                        <i class="fa-solid fa-location-dot"></i> Suivi colis
                    </a>
                    <a href="contact.php" class="btn btn-outline">
                        <i class="fa-solid fa-phone"></i> Contact
                    </a>
                    <a href="modifier-profil.php" class="btn btn-outline">
                        <i class="fa-solid fa-user-pen"></i> Modifier profil
                    </a>
                    <a href="auth.php?logout=1" class="btn btn-danger">
                        <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
                    </a>
                </div>
            </div>
        </div>

        <h2 class="section-title">
            <i class="fa-solid fa-box"></i> Mes colis postés
        </h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Image</th>
                        <th>Type</th>
                        <th>Poids (kg)</th>
                        <th>Destination</th>
                        <th>Date limite</th>
                        <th>Prix estimé</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colis as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nom_colis']) ?></td>
                            <td>
                                <?php if ($c['image_colis']): ?>
                                    <img src="<?= htmlspecialchars($c['image_colis']) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:4px;">
                                <?php else: ?>
                                    <img src="assets/default-colis.png" style="width:60px;height:60px;object-fit:cover;border-radius:4px;">
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($c['type_produit']) ?></td>
                            <td><?= htmlspecialchars($c['poids']) ?></td>
                            <td><?= htmlspecialchars($c['pays']) ?>, <?= htmlspecialchars($c['ville']) ?></td>
                            <td><?= htmlspecialchars($c['date_limite']) ?></td>
                            <td><?= htmlspecialchars($c['prix_estime']) ?> Fcfa</td>
                            <td>
                                <?php
                                if ($c['statut'] == 'en_attente') echo '<span class="badge badge-warning">En attente</span>';
                                elseif ($c['statut'] == 'approuve') echo '<span class="badge badge-success">Approuvé</span>';
                                elseif ($c['statut'] == 'refuse') echo '<span class="badge badge-danger">Refusé</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="section-title">
            <i class="fa-solid fa-truck"></i> Mes voyages proposés
        </h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Départ</th>
                        <th>Destination</th>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Poids max (kg)</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($voyages as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['pays_depart']) ?></td>
                            <td><?= htmlspecialchars($v['pays_destination']) ?></td>
                            <td><?= htmlspecialchars($v['date_depart']) ?></td>
                            <td><?= htmlspecialchars($v['heure_depart']) ?></td>
                            <td><?= htmlspecialchars($v['poids_max']) ?></td>
                            <td><?= htmlspecialchars($v['email']) ?></td>
                            <td><?= htmlspecialchars($v['telephone']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navbarToggler = document.getElementById('navbarToggler');
            const navbarMenu = document.getElementById('navbarMenu');

            if (navbarToggler && navbarMenu) {
                navbarToggler.addEventListener('click', function() {
                    navbarMenu.classList.toggle('active');

                
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-bars');
                    icon.classList.toggle('fa-times');
                });

                
                document.querySelectorAll('.nav-link, .btn-deconnexion').forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 992) {
                            navbarMenu.classList.remove('active');
                            const icon = navbarToggler.querySelector('i');
                            icon.classList.replace('fa-times', 'fa-bars');
                        }
                    });
                });
            }

            
            function adaptTables() {
                const tables = document.querySelectorAll('.table-container table');
                const screenWidth = window.innerWidth;

                tables.forEach(table => {
                    if (screenWidth <= 480) {
                        table.classList.add('mobile-optimized');
                    } else {
                        table.classList.remove('mobile-optimized');
                    }
                });
            }

            
            adaptTables();
            window.addEventListener('resize', adaptTables);
        });
    </script>
</body>

</html>
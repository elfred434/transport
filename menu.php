<?php
require_once __DIR__ . '/functions.php';
// Menu latéral partagé : accessible à tout utilisateur connecté (la garde
// d'authentification est assurée par chaque page qui inclut ce menu).
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Menu Transport Colis</title>
  <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
  <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
        }

        .vertical-menu {
            width: 250px;
            height: 100vh;
            background-color:  #3498db;
            color: white;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
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

        .content {
            flex: 1;
            padding: 20px;
            margin-left: 250px;
        }
    </style>
</head>
<body>

   <div class="vertical-menu">
        <div class="logo-container">
           <a href="index.php"><img src="OIG1.jpeg" alt="Logo SPIISTMOVE" class="logo" ></a> 
        </div>
        <ul class="menu-items">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Accueil</a></li>
            <li><a href="poster-colis.php"><i class="fas fa-box"></i> Postez Colis</a></li>
            <li><a href="profil.php"><i class="fas fa-user"></i> Profil</a></li>
            <li><a href="liste-messagerie.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="devenir-transporteur.php"><i class="fas fa-id-badge"></i> Devenir Transporteur</a></li>
            <li><a href="colis.php"><i class="fas fa-box-open"></i> Colis</a></li>
            <li><a href="suivi.php"><i class="fas fa-search-location"></i> Suivi</a></li>
            <li><a href="contact.php"><i class="fas fa-phone-alt"></i> Contact</a></li>
            
            <?php
            // Vérifier si l'utilisateur est un transporteur
            if (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                $stmt = $pdo->prepare("SELECT id FROM transporteurs WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $transporteur = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($transporteur) {
                    echo '<li><a href="profil-transporteur.php?id=' . $transporteur['id'] . '"><i class="fas fa-user-tie"></i>Profil Transporteur</a></li>';
                }
            }
            ?>

            <li class="nav-item">
                <a class="nav-link" href="reponses.php">
                    <i class="fas fa-reply"></i> Mes Réponses
                    <?php 
                    // Afficher un badge si des réponses non lues existent
                    if (isset($_SESSION['email'])) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages_contact WHERE email = ? AND reponse IS NOT NULL AND lu_par_utilisateur = 0");
                        $stmt->execute([$_SESSION['email']]);
                        $nb_reponses_non_lues = $stmt->fetchColumn();
                        
                        if ($nb_reponses_non_lues > 0) {
                            echo '<span class="badge bg-danger">'.$nb_reponses_non_lues.'</span>';
                        }
                    }
                    ?>
                </a>
            </li>
            <li><a href="messagerie-admin.php"><i class="fas fa-headset"></i> Contacter l'admin</a></li>
            <li><a href="auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>
</body>
</html>
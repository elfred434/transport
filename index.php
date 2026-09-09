<?php
require_once __DIR__ . '/functions.php';
$is_connected = isset($_SESSION['user_id']);


$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Supprime le message après l'avoir affiché
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agence de Transport de Colis</title>

    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">

</head>
<style>
        .alert-message {
            position: fixed;
            top: 200px;
            left: 500px;
            z-index: 1000;
            padding: 15px 20px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: fadeOut 5s forwards;
        }

        @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }
    </style>
<body>
<style>
    li{list-style: none;}
</style>
<?php if ($message): ?>
    <div class="alert-message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
    <nav class="main-navbar">
        <div class="container-navbar">
            <a href="index.php" class="nav-logo">
                <img src="OIG1.jpeg" alt="Logo"
                    style="height:38px;vertical-align:middle;border-radius:50%;margin-right:8px;">
                <span>SPIISTMOVE</span>
            </a>
            <button class="menu-toggle" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="nav-links">
                <li><a href="index.php">Accueil</a></li>
                <?php if ($is_connected): ?>
                    <li><a href="profil.php">Profil</a></li>
                    <li><a href="liste-messagerie.php">Messagerie</a></li>
                    <li><a href="poster-colis.php">Poster un colis</a></li>
                    <li><a href="devenir-transporteur.php">Devenir transporteur</a></li>
                    <li><a href="auth.php?logout=1">Déconnexion</a></li>
                <?php else: ?>
                    <li><a href="login.php">Connexion</a></li>
                    <li><a href="register.php">Inscription</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <header>
        <div class="container header-content">
            <div class="header-text">
                <h1>Envoyez vos colis partout,<br>simplement et rapidement !</h1>

            </div>
            <div class="btn-group">
                <a href="poster-colis.php" class="btn btn-primary">
                    <i class="fas fa-box"></i> Poster un colis
                </a>
                <a href="devenir-transporteur.php" class="btn btn-outline">
                    <i class="fas fa-truck"></i> Devenir transporteur
                </a>
            </div>
        </div>
    </header>
    <main class="container">
        
        <section class="section">
            <h2 class="section-title">Comment ça marche ?</h2>
          
                <style>
                    .btn-m{
                        padding: 10px 20px;
                        background-color: #007bff;
                        color: white;
                        border: none;
                        border-radius: 5px;
                        cursor: pointer;
                        font-size: 16px;
                        transition: background-color 0.3s ease;
                    }
                    .btn-m:hover{
                        background-color: #0056b3;
                    }
                    .modale{
                        display: none;
                        position: fixed;
                        z-index: 100;
                        left: 0;
                        top: 0;
                        width: 100%;
                        height: 100%;
                        overflow: auto;
                        background-color: rgba(0,123,255,1,0.4);
                    }
                    .contenu-modale{
                        background-color: #ffffff;
                        /* margin: 5% auto; */
                        /* padding: 20px; */
                        border: 1px solid #ddd;
                        max-width: 700px;
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                        border-radius: 10px;
                        position: relative;
                    }
                    .fermer{
                        color: #aaa;
                        position: absolute;
                        top: 10px;
                        right: 25px;
                        font-size: 35px;
                        font-weight: bold;
                        transition: color 0.3s ease;
                    }
                    .fermer:hover,
                    .fermer:focus{
                        color: #007bff;
                        text-decoration: none;
                        cursor: pointer;
                    }
                </style>
            </div>
            <div class="grid grid-3">
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3 class="card-title">1. Déposez votre annonce</h3>
                    <p>Décrivez votre colis et sa destination en quelques
                        clics.</p>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="card-title">2. Trouvez un transporteur</h3>
                    <p>Sélectionnez un transporteur disponible et
                        fiable.</p>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                    <h3 class="card-title">3. Envoyez votre colis</h3>
                    <p>Suivez la livraison en temps réel jusqu'à la
                        réception.</p>
                </div>
            </div>
        </section>

        
        <section class="section">
            <h2 class="section-title">Nos avantages</h2>
            <div class="grid grid-3">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <h3 class="feature-title">Sécurité</h3>
                    <p>Assurance colis et suivi en temps réel.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="feature-title">Rapidité</h3>
                    <p>Livraison express et transporteurs partout en
                        France.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <h3 class="feature-title">Économie</h3>
                    <p>Tarifs compétitifs et offres personnalisées.</p>
                </div>
            </div>
        </section>

        
        <section class="section">
            <h2 class="section-title">Nos chiffres clés</h2>
            <div class="grid grid-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number">+10 000</div>
                    <p>Utilisateurs inscrits</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-boxes-packing"></i>
                    </div>
                    <div class="stat-number">+25 000</div>
                    <p>Colis envoyés</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="stat-number">15</div>
                    <p>Pays desservis</p>
                </div>
            </div>
        </section>

        
        <section class="section">
            <h2 class="section-title">Ils nous font confiance</h2>
            <div class="grid grid-3">
                <div class="testimonial">
                    <p class="testimonial-text">Service rapide et fiable,
                        mon colis est arrivé en avance !</p>
                    <p class="testimonial-author">- Marie D.</p>
                </div>
                <div class="testimonial">
                    <p class="testimonial-text">J'ai pu envoyer un colis à
                        l'étranger sans stress. Merci !</p>
                    <p class="testimonial-author">- Ahmed B.</p>
                </div>
                <div class="testimonial">
                    <p class="testimonial-text">Interface simple et
                        transporteurs très professionnels.</p>
                    <p class="testimonial-author">- Sophie L.</p>
                </div>
            </div>
        </section>
    </main>
    <!-- Bloc bouton vidéo explicative -->
    <div class="text-center my-5">
        <button id="ouvrirModale" class="btn btn-primary" style="font-size:1.1rem;">
            <i class="fas fa-play-circle"></i> Voir la vidéo explicative
        </button>
    </div>

    <!-- Modale vidéo -->
    <div class="modale" id="modaleVideo" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;">
        <div class="contenu-modale" style="background:#fff;max-width:700px;width:90%;border-radius:12px;box-shadow:0 8px 32px #0003;position:relative;padding:1.5rem;">
            <span class="fermer" style="position:absolute;top:10px;right:20px;font-size:2rem;cursor:pointer;color:#2563eb;">&times;</span>
            <video src="Animaker.mp4" id="video" style="width:100%;height:auto;max-height:60vh;border-radius:8px;" controls></video>
        </div>
    </div>
    <footer>
        <div class="container">
            <div class="footer-links">
                <a href="#" class="footer-link">Contact</a>
                <a href="#" class="footer-link">FAQ</a>
                <a href="#" class="footer-link">CGU</a>
            </div>
            <p class="copyright">&copy; 2025 Agence de Transport de
                Colis</p>
        </div>
    </footer>
    <script>
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('active');
        });

        // Ferme le menu mobile quand on clique sur un lien (optionnel)
        document.querySelectorAll('.nav-links a').forEach(function(link) {
            link.addEventListener('click', function() {
                if(window.innerWidth <= 640){
                    document.querySelector('.nav-links').classList.remove('active');
                }
            });
        });
    </script>
    <script>
document.getElementById('ouvrirModale').onclick = function () {
    document.getElementById('modaleVideo').style.display = "flex";
    document.getElementById('video').play();
};
document.querySelector('.fermer').onclick = function () {
    document.getElementById('modaleVideo').style.display = "none";
    document.getElementById('video').pause();
};
window.onclick = function (event) {
    if (event.target == document.getElementById('modaleVideo')) {
        document.getElementById('modaleVideo').style.display = "none";
        document.getElementById('video').pause();
    }
};
</script>
</body>

</html>
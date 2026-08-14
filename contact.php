<?php
session_start();

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


$nom_session = isset($_SESSION['prenom']) && isset($_SESSION['nom']) ? $_SESSION['prenom'] . ' ' . $_SESSION['nom'] : '';
$email_session = isset($_SESSION['email']) ? $_SESSION['email'] : '';

$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = htmlspecialchars(trim($_POST['nom']));
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $message = htmlspecialchars(trim($_POST['message']));
    if ($nom && $email && $message) {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages_contact (nom, email, message) VALUES (?, ?, ?)");
            $stmt->execute([$nom, $email, $message]);
            $success = true;
        } catch (Exception $e) {
            $error = "Erreur lors de l'enregistrement du message.";
        }
    } else {
        $error = "Tous les champs sont obligatoires et l'email doit être valide.";
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contactez-nous - Agence de Transport de Colis</title>
    <link rel="stylesheet" href="style.css">
    
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; margin: 0; }
        .container { max-width: 900px; margin: 40px auto; background: #fff; padding: 32px; border-radius: 10px; box-shadow: 0 2px 8px #0001; }
        h2 { color: #007bff; text-align: center; margin-bottom: 24px; }
        .contact-row { display: flex; flex-wrap: wrap; gap: 32px; }
        .contact-form, .contact-infos { flex: 1 1 320px; }
        form label { display: block; margin-bottom: 6px; font-weight: bold; }
        form input[type="text"], form input[type="email"], form textarea {
            width: 100%; padding: 10px; margin-bottom: 18px; border: 1px solid #ccc; border-radius: 4px;
        }
        form textarea { min-height: 100px; resize: vertical; }
        button {
            width: 100%; padding: 12px; background: #007bff; color: #fff; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer;
        }
        button:hover { background: #0056b3; }
        .contact-infos {
            background: #f1f8ff;
            border-radius: 6px;
            padding: 18px;
            margin-bottom: 18px;
            font-size: 16px;
            color: #34495e;
        }
        .contact-infos i { color: #007bff; margin-right: 8px; }
        .map-container {
            width: 100%;
            height: 260px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 4px #0001;
        }
        .msg-success { color: #27ae60; font-weight: bold; margin-bottom: 18px; }
        .msg-error { color: #e74c3c; font-weight: bold; margin-bottom: 18px; }
        @media (max-width: 900px) {
            .contact-row { flex-direction: column; }
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
<div class="content">
    <div class="container">
        <h2><i class="fa-solid fa-envelope"></i> Contactez-nous</h2>
        <div class="contact-row">
            <div class="contact-form">
                <?php if ($success): ?>
                    <div class="msg-success">Votre message a bien été envoyé. Merci !</div>
                <?php elseif ($error): ?>
                    <div class="msg-error"><?= $error ?></div>
                <?php endif; ?>
                <form method="post" action="contact.php">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" required 
    value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ($nom_session ?: '') ?>">

                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required 
    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ($email_session ?: '') ?>">

                    <label for="message">Message</label>
                    <textarea id="message" name="message" required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>

                    <button type="submit"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
                </form>
            </div>
      
            <div>
                <div class="contact-infos">
                    <div><i class="fa-solid fa-envelope"></i> contact@transportcolis.com</div>
                    <div><i class="fa-solid fa-phone"></i> +229 97 00 00 00</div>
                    <div><i class="fa-solid fa-location-dot"></i> Porto-Novo, Quartier Hinkoudé, Bénin</div>
                </div>
                <div class="map-container">
                    <iframe 
                        src="https://www.openstreetmap.org/export/embed.html?bbox=2.6015%2C6.4855%2C2.6115%2C6.4955&amp;layer=mapnik&amp;marker=6.4905,2.6065"
                        width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
                  <div class="text-center mt-4">
    <a href="reponses.php" class="btn btn-outline-primary">
        <i class="fas fa-reply"></i> Voir mes réponses
    </a>
</div>
        </div>
    </div>
</div>
    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
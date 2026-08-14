<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['email'])) {
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

// Récupérer les réponses pour l'utilisateur connecté
$email = $_SESSION['email'];
$stmt = $pdo->prepare("SELECT * FROM messages_contact WHERE email = ? AND reponse IS NOT NULL ORDER BY date_reponse DESC");
$stmt->execute([$email]);
$reponses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer les réponses comme lues
if (!empty($reponses)) {
    $pdo->prepare("UPDATE messages_contact SET lu_par_utilisateur = 1 WHERE email = ? AND reponse IS NOT NULL")->execute([$email]);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Réponses - Agence de Transport de Colis</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            padding: 32px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2 {
            color: #007bff;
            text-align: center;
            margin-bottom: 24px;
        }
        .card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-weight: bold;
        }
        .badge-new {
            background-color: #dc3545;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 50px;
            margin-bottom: 20px;
            color: #dee2e6;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="content">
        <div class="container">
            <h2><i class="fas fa-reply"></i> Mes Réponses</h2>
            
            <?php if (!empty($reponses)): ?>
                <?php foreach($reponses as $reponse): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>
                                Message envoyé le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($reponse['date_envoi']))) ?>
                            </span>
                            <?php if (!$reponse['lu_par_utilisateur']): ?>
                                <span class="badge badge-new">Nouveau</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Votre message :</h5>
                            <p class="card-text"><?= htmlspecialchars($reponse['message']) ?></p>
                            
                            <hr>
                            
                            <h5 class="card-title">Réponse de l'équipe :</h5>
                            <p class="card-text"><?= htmlspecialchars($reponse['reponse']) ?></p>
                            <small class="text-muted">
                                Réponse envoyée le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($reponse['date_reponse']))) ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="far fa-envelope-open"></i>
                    <h3>Aucune réponse disponible</h3>
                    <p>Vous n'avez pas encore reçu de réponse à vos messages.</p>
                    <a href="contact.php" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Envoyer un message
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
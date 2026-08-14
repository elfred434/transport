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

// Récupérer les messages entre l'utilisateur et l'administrateur
$stmt = $pdo->prepare("
    SELECT * FROM messages_admin
    WHERE user_id = ?
    ORDER BY date_envoi ASC
");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Envoi d'un nouveau message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contenu'])) {
    $contenu = htmlspecialchars(trim($_POST['contenu']));
    if (!empty($contenu)) {
        $stmt = $pdo->prepare("INSERT INTO messages_admin (user_id, contenu) VALUES (?, ?)");
        $stmt->execute([$user_id, $contenu]);
        header('Location: messagerie-client.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie avec l'Admin</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        .message-row { margin-bottom: 15px; }
        .message-row.admin { text-align: left; }
        .message-row.user { text-align: right; }
        .message-bubble {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 15px;
            max-width: 70%;
        }
        .message-bubble.admin { background-color: #f1f1f1; color: #333; }
        .message-bubble.user { background-color: #007bff; color: #fff; }
    </style>
</head>
<body>
<div class="container my-5">
    <h2 class="text-center text-primary">Messagerie avec l'Admin</h2>
    <div class="messages-zone border p-3 mb-4" style="height: 400px; overflow-y: auto;">
        <?php foreach ($messages as $msg): ?>
            <div class="message-row <?= $msg['admin_id'] ? 'admin' : 'user' ?>">
                <div class="message-bubble <?= $msg['admin_id'] ? 'admin' : 'user' ?>">
                    <?= htmlspecialchars($msg['contenu']) ?>
                </div>
                <small class="text-muted d-block"><?= date('d/m/Y H:i', strtotime($msg['date_envoi'])) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="POST" class="d-flex">
        <textarea name="contenu" class="form-control me-2" placeholder="Écrivez un message..." required></textarea>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
    </form>
</div>
<script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
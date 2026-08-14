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
$is_admin = ($_SESSION['role'] === 'admin');
$admin_id = 1; // ID de l'administrateur (à adapter selon votre base de données)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie Admin</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; margin: 0; }
        .container { max-width: 800px; margin: 40px auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px #0001; }
        .messages { max-height: 400px; overflow-y: auto; margin-bottom: 20px; }
        .message { padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .message.admin { background: #007bff; color: #fff; text-align: right; }
        .message.user { background: #f1f1f1; color: #333; text-align: left; }
        .form-control { resize: none; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center"><i class="fa-solid fa-envelope"></i> Messagerie</h2>
    <div class="messages" id="messages"></div>
    <form id="messageForm">
        <textarea class="form-control" id="messageContent" rows="3" placeholder="Écrivez votre message..." required></textarea>
        <button type="submit" class="btn btn-primary w-100 mt-2"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    const userId = <?= $user_id ?>;
    const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;
    const adminId = <?= $admin_id ?>;

    function loadMessages() {
        $.get('ajax_get_messages.php', { user_id: userId, is_admin: isAdmin }, function(data) {
            $('#messages').html(data);
            $('#messages').scrollTop($('#messages')[0].scrollHeight);
        });
    }

    $('#messageForm').on('submit', function(e) {
        e.preventDefault();
        const content = $('#messageContent').val();
        if (content.trim() === '') return;

        $.post('ajax_send_message.php', {
            expediteur_id: userId,
            destinataire_id: isAdmin ? $('#userId').val() : adminId,
            contenu: content
        }, function() {
            $('#messageContent').val('');
            loadMessages();
        });
    });

    setInterval(loadMessages, 3000); // Rafraîchit les messages toutes les 3 secondes
    loadMessages();
</script>
</body>
</html>
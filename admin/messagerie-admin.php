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
    SELECT m.*, u.nom AS admin_nom, u.prenom AS admin_prenom
    FROM messages_admin m
    LEFT JOIN users u ON m.admin_id = u.id
    WHERE m.user_id = ?
    ORDER BY m.date_envoi ASC
");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Messagerie avec l'Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            margin: 0;
            padding: 0;
        }
        .messagerie-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .messages-zone {
            height: 400px;
            overflow-y: auto;
            padding: 20px;
            background: #f9f9f9;
        }
        .message-row {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        .message-row.me {
            justify-content: flex-end;
        }
        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 10px;
            background: #e0e0e0;
            color: #333;
        }
        .message-row.me .message-bubble {
            background: #007bff;
            color: #fff;
        }
        .chat-footer {
            display: flex;
            padding: 10px;
            border-top: 1px solid #ddd;
        }
        .chat-footer textarea {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: none;
        }
        .chat-footer button {
            margin-left: 10px;
            padding: 10px 20px;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="messagerie-container">
    <div class="messages-zone" id="messages-zone">
        <?php foreach ($messages as $msg): ?>
            <div class="message-row <?= $msg['admin_id'] ? '' : 'me' ?>">
                <div class="message-bubble">
                    <?= htmlspecialchars($msg['contenu']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <form id="form-message" class="chat-footer" enctype="multipart/form-data">
        <textarea name="contenu" id="contenu" placeholder="Écrivez un message..." required></textarea>
        <button type="submit">Envoyer</button>
    </form>
</div>
<script>
    document.getElementById('form-message').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('ajax_messagerie_admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const messagesZone = document.getElementById('messages-zone');
                const newMessage = document.createElement('div');
                newMessage.className = 'message-row me';
                newMessage.innerHTML = `<div class="message-bubble">${data.contenu}</div>`;
                messagesZone.appendChild(newMessage);
                messagesZone.scrollTop = messagesZone.scrollHeight;
                this.reset();
            }
        });
    });
</script>
</body>
</html>
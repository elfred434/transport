<?php
require_once __DIR__ . '/functions.php';
require_admin();

$messages = $pdo->query("SELECT * FROM messages_contact ORDER BY date_envoi DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Messages de contact - Admin</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container mt-4">
        <h2>Messages reçus via le formulaire de contact</h2>
        <table class="table table-bordered table-striped mt-3">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $msg): ?>
                <tr>
                    <td><?= htmlspecialchars($msg['date_envoi']) ?></td>
                    <td><?= htmlspecialchars($msg['nom']) ?></td>
                    <td><?= htmlspecialchars($msg['email']) ?></td>
                    <td><?= nl2br(htmlspecialchars($msg['message'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
/**
 * Messagerie utilisateur ↔ administrateur (page utilisateur).
 */
require_once __DIR__ . '/functions.php';
require_login();

$user_id  = (int) $_SESSION['user_id'];
$admin_id = get_admin_id();
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
        .message.admin { background: #007bff; color: #fff; }
        .message.user { background: #f1f1f1; color: #333; text-align: right; }
        .form-control { resize: none; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center"><i class="fa-solid fa-headset"></i> Contacter l'administrateur</h2>

    <?php if (!$admin_id): ?>
        <div class="alert alert-warning">Aucun administrateur n'est disponible pour le moment.</div>
    <?php else: ?>
        <div class="messages" id="messages"><p class="text-muted text-center">Chargement…</p></div>
        <form id="messageForm">
            <textarea class="form-control" id="messageContent" rows="3" maxlength="5000" placeholder="Écrivez votre message..." required></textarea>
            <button type="submit" class="btn btn-primary w-100 mt-2"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
        </form>
        <div id="formError" class="alert alert-danger mt-2 d-none"></div>
        <p class="text-center mt-3"><a href="dashboard.php">← Retour au tableau de bord</a></p>
    <?php endif; ?>
</div>

<script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<?php if ($admin_id): ?>
<script>
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

    function loadMessages() {
        fetch('ajax_get_messages.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const zone = document.getElementById('messages');
                const atBottom = zone.scrollTop + zone.clientHeight >= zone.scrollHeight - 40;
                zone.innerHTML = html;
                if (atBottom) zone.scrollTop = zone.scrollHeight;
            })
            .catch(() => {});
    }

    document.getElementById('messageForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const textarea = document.getElementById('messageContent');
        const contenu = textarea.value.trim();
        if (contenu === '') return;

        const body = new URLSearchParams();
        body.append('contenu', contenu);

        fetch('ajax_send_message.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: body
        })
        .then(r => r.json())
        .then(data => {
            const err = document.getElementById('formError');
            if (data.success) {
                textarea.value = '';
                err.classList.add('d-none');
                loadMessages();
            } else {
                err.textContent = data.error || "Erreur lors de l'envoi.";
                err.classList.remove('d-none');
            }
        })
        .catch(() => {
            const err = document.getElementById('formError');
            err.textContent = "Erreur réseau lors de l'envoi.";
            err.classList.remove('d-none');
        });
    });

    loadMessages();
    setInterval(loadMessages, 5000);
</script>
<?php endif; ?>
</body>
</html>

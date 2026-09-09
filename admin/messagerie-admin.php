<?php
/**
 * Console admin de la messagerie : liste des conversations utilisateur ↔ admin
 * et réponse à chaque utilisateur.
 */
require_once __DIR__ . '/../functions.php';
require_admin();

$adminId = (int) $_SESSION['user_id'];

// Conversations distinctes avec dernier message et nb de non-lus
$conversations = $pdo->prepare(
    "SELECT u.id AS user_id, u.nom, u.prenom, u.email,
            (SELECT contenu FROM messages_admin m2
              WHERE (m2.expediteur_id = u.id AND m2.destinataire_id = :admin1)
                 OR (m2.expediteur_id = :admin2 AND m2.destinataire_id = u.id)
              ORDER BY m2.date_envoi DESC LIMIT 1) AS dernier_message,
            (SELECT date_envoi FROM messages_admin m3
              WHERE (m3.expediteur_id = u.id AND m3.destinataire_id = :admin3)
                 OR (m3.expediteur_id = :admin4 AND m3.destinataire_id = u.id)
              ORDER BY m3.date_envoi DESC LIMIT 1) AS derniere_date,
            (SELECT COUNT(*) FROM messages_admin m4
              WHERE m4.expediteur_id = u.id AND m4.destinataire_id = :admin5 AND m4.lu = 0) AS non_lus
     FROM users u
     WHERE u.id != :admin6
       AND EXISTS (SELECT 1 FROM messages_admin m5
                    WHERE (m5.expediteur_id = u.id AND m5.destinataire_id = :admin7)
                       OR (m5.expediteur_id = :admin8 AND m5.destinataire_id = u.id))
     ORDER BY derniere_date DESC"
);
$conversations->execute([
    'admin1' => $adminId, 'admin2' => $adminId, 'admin3' => $adminId, 'admin4' => $adminId,
    'admin5' => $adminId, 'admin6' => $adminId, 'admin7' => $adminId, 'admin8' => $adminId,
]);
$conversations = $conversations->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie — Administration</title>
    <link rel="stylesheet" href="../bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f8fb; margin: 0; }
        .wrap { max-width: 1100px; margin: 30px auto; padding: 0 15px; }
        .layout { display: flex; gap: 15px; align-items: stretch; }
        .conv-list { width: 320px; flex-shrink: 0; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px #0001; overflow-y: auto; max-height: 70vh; }
        .conv-item { display: block; width: 100%; text-align: left; padding: 12px 15px; border: none; border-bottom: 1px solid #eee; background: #fff; cursor: pointer; }
        .conv-item:hover, .conv-item.active { background: #eaf3fd; }
        .conv-item .nom { font-weight: bold; }
        .conv-item .apercu { font-size: .85em; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px; }
        .chat { flex: 1; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px #0001; padding: 20px; display: flex; flex-direction: column; }
        .messages { flex: 1; max-height: 50vh; overflow-y: auto; margin-bottom: 15px; }
        .message { padding: 10px; border-radius: 8px; margin-bottom: 10px; max-width: 85%; }
        .message.admin { background: #007bff; color: #fff; margin-left: auto; }
        .message.user { background: #f1f1f1; color: #333; }
        .form-control { resize: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fa-solid fa-envelope"></i> Messagerie utilisateurs</h2>
        <a href="admin.php" class="btn btn-outline-secondary">← Retour administration</a>
    </div>

    <div class="layout">
        <div class="conv-list">
            <?php if (!$conversations): ?>
                <p class="text-muted p-3 mb-0">Aucune conversation pour l'instant.</p>
            <?php endif; ?>
            <?php foreach ($conversations as $c): ?>
                <button type="button" class="conv-item" data-user-id="<?= (int) $c['user_id'] ?>"
                        data-nom="<?= e($c['prenom'] . ' ' . $c['nom']) ?>">
                    <div class="nom">
                        <?= e($c['prenom'] . ' ' . $c['nom']) ?>
                        <?php if ((int) $c['non_lus'] > 0): ?>
                            <span class="badge bg-danger"><?= (int) $c['non_lus'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="apercu"><?= e(mb_substr((string) $c['dernier_message'], 0, 60)) ?></div>
                    <small class="text-muted"><?= e($c['derniere_date']) ?></small>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="chat">
            <h5 id="chatTitle" class="text-muted">Sélectionnez une conversation</h5>
            <div class="messages" id="messages"></div>
            <form id="replyForm" class="d-none">
                <textarea class="form-control" id="replyContent" rows="3" maxlength="5000" placeholder="Votre réponse..." required></textarea>
                <button type="submit" class="btn btn-primary w-100 mt-2"><i class="fa-solid fa-paper-plane"></i> Répondre</button>
            </form>
            <div id="chatError" class="alert alert-danger mt-2 d-none"></div>
        </div>
    </div>
</div>

<script src="../bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
    let currentUserId = null;
    let refreshTimer = null;

    function loadMessages() {
        if (!currentUserId) return;
        fetch('ajax_get_messages.php?user_id=' + encodeURIComponent(currentUserId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.text())
        .then(html => {
            const zone = document.getElementById('messages');
            const atBottom = zone.scrollTop + zone.clientHeight >= zone.scrollHeight - 40;
            zone.innerHTML = html;
            if (atBottom) zone.scrollTop = zone.scrollHeight;
        })
        .catch(() => {});
    }

    document.querySelectorAll('.conv-item').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.conv-item').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentUserId = this.dataset.userId;
            document.getElementById('chatTitle').textContent = 'Conversation avec ' + this.dataset.nom;
            document.getElementById('replyForm').classList.remove('d-none');
            const badge = this.querySelector('.badge');
            if (badge) badge.remove();
            loadMessages();
            clearInterval(refreshTimer);
            refreshTimer = setInterval(loadMessages, 5000);
        });
    });

    document.getElementById('replyForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const textarea = document.getElementById('replyContent');
        const contenu = textarea.value.trim();
        if (contenu === '' || !currentUserId) return;

        const body = new URLSearchParams();
        body.append('destinataire_id', currentUserId);
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
            const err = document.getElementById('chatError');
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
            const err = document.getElementById('chatError');
            err.textContent = "Erreur réseau lors de l'envoi.";
            err.classList.remove('d-none');
        });
    });
</script>
</body>
</html>

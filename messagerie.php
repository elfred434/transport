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

$expediteur_id = $_SESSION['user_id'];
$destinataire_id = isset($_GET['destinataire_id']) ? intval($_GET['destinataire_id']) : 0;
$colis_id = isset($_GET['colis_id']) ? intval($_GET['colis_id']) : null;
$voyage_id = isset($_GET['voyage_id']) ? intval($_GET['voyage_id']) : null;


$convs = $pdo->prepare("
    SELECT u.id, u.nom, u.prenom, u.photo_profil,
        MAX(m.date_envoi) as last_msg, 
        (SELECT contenu FROM messages WHERE (expediteur_id = :me AND destinataire_id = u.id) OR (expediteur_id = u.id AND destinataire_id = :me) ORDER BY date_envoi DESC LIMIT 1) as last_content
    FROM users u
    JOIN messages m ON (m.expediteur_id = u.id OR m.destinataire_id = u.id)
    WHERE u.id != :me AND (m.expediteur_id = :me OR m.destinataire_id = :me)
    GROUP BY u.id
    ORDER BY last_msg DESC
");
$convs->execute(['me' => $expediteur_id]);
$conversations = $convs->fetchAll(PDO::FETCH_ASSOC);


$messages = [];
$destinataire = ['nom' => '', 'prenom' => ''];
if ($destinataire_id) {
    $stmt = $pdo->prepare("SELECT m.*, u.nom, u.prenom, u.photo_profil FROM messages m
        LEFT JOIN users u ON m.expediteur_id = u.id
        WHERE ((m.expediteur_id = ? AND m.destinataire_id = ?) OR (m.expediteur_id = ? AND m.destinataire_id = ?))
        ORDER BY m.date_envoi ASC");
    $stmt->execute([$expediteur_id, $destinataire_id, $destinataire_id, $expediteur_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT nom, prenom, photo_profil FROM users WHERE id = ?");
    $stmt->execute([$destinataire_id]);
    $destinataire = $stmt->fetch(PDO::FETCH_ASSOC);
}


$is_online = $destinataire_id && (time() % 2 == 0);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Messagerie</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:400,500,600&display=swap">
    
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
   
    <style>
        /* filepath: c:\xampp\htdocs\transport\css\messagerie.css */
        :root {
            --main-bg: #f6f8fb;
            --panel-bg: #fff;
            --primary: #1a237e;
            --primary-light: #3f51b5;
            --accent: #00bcd4;
            --gray: #e0e7ef;
            --gray-dark: #b0b8c1;
            --bubble-me: #1a237e;
            --bubble-other: #f1f5fa;
            --bubble-shadow: 0 2px 12px #1a237e11;
            --radius: 18px;
            --transition: all .25s cubic-bezier(.4, 0, .2, 1);
        }

        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Inter', Arial, sans-serif;
            background: var(--main-bg);
            min-height: 100vh;
            overflow: hidden;
        }

        .messagerie-app {
            display: flex;
            height: 100vh;
            max-height: 100vh;
        }

        /* Panel gauche - Liste des conversations */
        .panel-left {
            width: 340px;
            background: var(--panel-bg);
            border-right: 1px solid var(--gray);
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 16px #1a237e0a;
            z-index: 2;
        }

        .panel-header {
            padding: 24px 18px 18px 18px;
            border-bottom: 1px solid var(--gray);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--gray);
        }

        .brand-title {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary);
            letter-spacing: 1px;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            margin: 0;
            background: var(--panel-bg);
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--gray);
            cursor: pointer;
            transition: var(--transition);
            background: var(--panel-bg);
            position: relative;
            text-decoration: none;
            color: inherit;
        }

        .conversation-item.active,
        .conversation-item:hover {
            background: #f0f4ff;
        }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary);
            object-fit: cover;
        }

        .conv-info {
            flex: 1;
            min-width: 0;
        }

        .conv-name {
            font-weight: 600;
            color: var(--primary);
            font-size: 1.08em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-last {
            color: var(--gray-dark);
            font-size: 0.97em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-date {
            font-size: 0.85em;
            color: var(--gray-dark);
            margin-left: 4px;
        }

        /* Panel droit - Conversation */
        .panel-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--main-bg);
            min-width: 0;
            position: relative;
        }

        .chat-header {
            background: var(--panel-bg);
            border-bottom: 1px solid var(--gray);
            padding: 18px 28px;
            display: flex;
            align-items: center;
            gap: 16px;
            min-height: 70px;
        }

        .chat-header .avatar {
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
        }

        .chat-header .chat-title {
            font-weight: 600;
            font-size: 1.13rem;
            color: var(--primary);
        }

        .status-dot {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            margin-left: 8px;
            background: #bbb;
            display: inline-block;
        }

        .status-dot.online {
            background: #00e676;
            box-shadow: 0 0 0 2px #e0ffe7;
        }

        .status-dot.offline {
            background: #bbb;
        }

        .messages-zone {
            flex: 1;
            overflow-y: auto;
            padding: 32px 0 24px 0;
            background: linear-gradient(180deg, #f6f8fb 80%, #e3eaf7 100%);
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .messages-group-date {
            text-align: center;
            color: var(--gray-dark);
            font-size: 0.98em;
            margin: 18px 0 10px 0;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .message-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 10px;
            animation: fadeInMsg 0.5s;
        }

        .message-row.me {
            justify-content: flex-end;
        }

        .message-bubble {
            max-width: 70vw;
            padding: 13px 18px;
            border-radius: var(--radius);
            background: var(--bubble-other);
            color: #222;
            box-shadow: var(--bubble-shadow);
            font-size: 1.07em;
            position: relative;
            word-break: break-word;
            transition: var(--transition);
        }

        .message-row.me .message-bubble {
            background: var(--bubble-me);
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .message-row.other .message-bubble {
            border-bottom-left-radius: 4px;
        }

        .message-meta {
            font-size: 0.85em;
            color: var(--gray-dark);
            margin-top: 6px;
            text-align: right;
        }

        .message-row.me .message-meta {
            color: #cbe2ff;
        }

        .message-appear {
            animation: fadeInMsg 0.5s;
        }

        @keyframes fadeInMsg {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* Pied de page - Formulaire d'envoi */
        .chat-footer {
            background: var(--panel-bg);
            border-top: 1px solid var(--gray);
            padding: 18px 28px;
            display: flex;
            align-items: flex-end;
            gap: 10px;
            min-height: 80px;
        }

        .chat-footer textarea {
            flex: 1;
            min-height: 44px;
            max-height: 120px;
            resize: none;
            border: 1px solid var(--gray);
            border-radius: var(--radius);
            padding: 12px 14px;
            font-size: 1.07em;
            font-family: inherit;
            transition: var(--transition);
            background: #fafdff;
        }

        .chat-footer textarea:focus {
            outline: none;
            border-color: var(--primary-light);
        }

        .btn-attach {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.3em;
            cursor: pointer;
            margin-right: 6px;
            transition: var(--transition);
        }

        .btn-attach:hover {
            color: var(--accent);
        }

        .btn-send {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            padding: 0 22px;
            font-size: 1.1em;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            height: 44px;
        }

        .btn-send:hover {
            background: var(--primary-light);
        }

        /* État vide - Aucune conversation sélectionnée */
        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: var(--gray-dark);
            font-size: 1.2em;
        }

        .empty-state i {
            font-size: 2em;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .messagerie-app {
                flex-direction: column;
            }

            .panel-left {
                width: 100vw;
                height: 120px;
                flex-direction: row;
                border-right: none;
                border-bottom: 1px solid var(--gray);
            }

            .panel-header {
                flex: 1;
                border-bottom: none;
                border-right: 1px solid var(--gray);
            }

            .conversations-list {
                flex: 2;
                display: flex;
                flex-direction: row;
                overflow-x: auto;
                overflow-y: hidden;
            }

            .conversation-item {
                flex-direction: column;
                align-items: flex-start;
                min-width: 180px;
                border-bottom: none;
                border-right: 1px solid var(--gray);
            }

            .panel-right {
                min-width: 0;
            }
        }

        @media (max-width: 600px) {
            .panel-left {
                display: none;
            }

            .panel-right {
                width: 100vw;
            }
        }
    </style>
    
</head>

<body>
    <div class="messagerie-app">
        
        <div class="panel-left">
            <div class="panel-header">
                <img src="OIG1.jpeg" class="logo" alt="Logo">
                <span class="brand-title">SPIISTMOVE</span>
            </div>
            <div class="conversations-list">
                <?php foreach ($conversations as $conv): ?>
                    <?php
                    $active = ($conv['id'] == $destinataire_id) ? 'active' : '';
                    $initials = strtoupper(substr($conv['prenom'], 0, 1) . substr($conv['nom'], 0, 1));
                    ?>
                    <a href="messagerie.php?destinataire_id=<?= $conv['id'] ?>" class="conversation-item <?= $active ?>">
                        <div class="avatar">
                            <?= $conv['photo_profil'] ? '<img src="' . htmlspecialchars($conv['photo_profil']) . '" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">' : $initials ?>
                        </div>
                        <div class="conv-info">
                            <div class="conv-name"><?= htmlspecialchars($conv['prenom'] . ' ' . $conv['nom']) ?></div>
                            <div class="conv-last"><?= htmlspecialchars($conv['last_content']) ?></div>
                        </div>
                        <div class="conv-date"><?= $conv['last_msg'] ? date('d/m H:i', strtotime($conv['last_msg'])) : '' ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="panel-right">
            <?php if ($destinataire_id): ?>
                <div class="chat-header">
                    <div class="avatar">
                        <?= $destinataire['photo_profil'] ? '<img src="' . htmlspecialchars($destinataire['photo_profil']) . '" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">' : strtoupper(substr($destinataire['prenom'], 0, 1) . substr($destinataire['nom'], 0, 1)) ?>
                    </div>
                    <div>
                        <span class="chat-title"><?= htmlspecialchars($destinataire['prenom'] . ' ' . $destinataire['nom']) ?></span>
                        <span class="status-dot <?= $is_online ? 'online' : 'offline' ?>" title="<?= $is_online ? 'En ligne' : 'Hors ligne' ?>"></span>
                    </div>
                </div>
                <div class="messages-zone" id="messages-zone">
                    
                </div>
                <form id="form-message" class="chat-footer" enctype="multipart/form-data" autocomplete="off">
                    <button type="button" class="btn-attach" title="Joindre un fichier" onclick="document.getElementById('file-input').click();">
                        <i class="fa-solid fa-paperclip"></i>
                    </button>
                    <input type="file" id="file-input" name="fichier" style="display:none">
                    <textarea name="contenu" id="contenu" placeholder="Écrivez un message..." required></textarea>
                    <button type="submit" class="btn-send">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            <?php else: ?>
                <div style="display:flex;align-items:center;justify-content:center;height:100%;">
                    <div style="text-align:center;color:#b0b8c1;font-size:1.2em;">
                        <i class="fa-regular fa-comments" style="font-size:2em;"></i><br>
                        Sélectionnez une conversation à gauche
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        const expediteur_id = <?= (int)$expediteur_id ?>;
        const destinataire_id = <?= (int)$destinataire_id ?>;

        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        }

        function groupByDate(messages) {
            const groups = {};
            messages.forEach(msg => {
                const d = new Date(msg.date_envoi);
                const dateStr = d.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
                if (!groups[dateStr]) groups[dateStr] = [];
                groups[dateStr].push(msg);
            });
            return groups;
        }

        function scrollToBottom() {
            const zone = document.getElementById('messages-zone');
            zone.scrollTop = zone.scrollHeight;
        }

        function chargerMessages() {
            if (!destinataire_id) return;
            fetch('ajax_messagerie.php?destinataire_id=' + destinataire_id)
                .then(r => r.json())
                .then(messages => {
                    const zone = document.getElementById('messages-zone');
                    
                    const isAtBottom = (zone.scrollTop + zone.clientHeight + 10 >= zone.scrollHeight);

                    
                    const frag = document.createDocumentFragment();
                    const groups = groupByDate(messages);
                    Object.keys(groups).forEach(dateStr => {
                        const dateDiv = document.createElement('div');
                        dateDiv.className = 'messages-group-date';
                        dateDiv.textContent = dateStr;
                        frag.appendChild(dateDiv);
                        groups[dateStr].forEach(msg => {
                            const isMe = (msg.expediteur_id == expediteur_id);
                            const row = document.createElement('div');
                            row.className = 'message-row ' + (isMe ? 'me' : 'other');
                            let fileHtml = '';
                            if (msg.fichier) {
                                const fileUrl = msg.fichier.replace(/^(\.\.\/)+/, ''); 
                                const ext = fileUrl.split('.').pop().toLowerCase();
                                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                                    fileHtml = `<div style="margin-top:8px;"><a href="${fileUrl}" target="_blank"><img src="${fileUrl}" alt="Fichier joint" style="max-width:160px;max-height:120px;border-radius:8px;box-shadow:0 2px 8px #0001;"></a></div>`;
                                } else {
                                    fileHtml = `<div style="margin-top:8px;"><a href="${fileUrl}" target="_blank" style="color:#1a237e;"><i class="fa-solid fa-paperclip"></i> Télécharger le fichier</a></div>`;
                                }
                            }
                            row.innerHTML = `
                        <div class="avatar" style="width:36px;height:36px;font-size:1em;">
                            ${msg.photo_profil ? `<img src="${escapeHtml(msg.photo_profil)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">` : escapeHtml((msg.prenom ? msg.prenom[0] : '') + (msg.nom ? msg.nom[0] : '')).toUpperCase()}
                        </div>
                        <div class="message-bubble">
                            <div>${escapeHtml(msg.contenu).replace(/\n/g, '<br>')}</div>
                            ${fileHtml}
                            <div class="message-meta">${new Date(msg.date_envoi).toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit'})}</div>
                        </div>
                    `;
                            frag.appendChild(row);
                        });
                    });

                    // Remplace le contenu sans flash
                    zone.innerHTML = '';
                    zone.appendChild(frag);

                    // Scroll auto uniquement si l'utilisateur était déjà en bas
                    if (isAtBottom) scrollToBottom();
                });
        }
        if (destinataire_id) {
            setInterval(chargerMessages, 300000);
            chargerMessages();
        }
        const textarea = document.getElementById('contenu');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }
        document.getElementById('form-message')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('destinataire_id', destinataire_id);
            fetch('ajax_messagerie.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        form.reset();
                        chargerMessages();
                    }
                });
        });
        document.getElementById('file-input')?.addEventListener('change', function() {
            if (this.files.length) {
                textarea.value += "\n[ " + this.files[0].name + "]";
                textarea.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>

</html>
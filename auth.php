<?php
/**
 * Authentification : inscription, connexion, déconnexion,
 * demande de réinitialisation et réinitialisation du mot de passe.
 */
require_once __DIR__ . '/functions.php';

// ---------------------------------------------------------------------------
// INSCRIPTION
// ---------------------------------------------------------------------------
if (isset($_POST['register'])) {
    csrf_check();

    $nom      = trim($_POST['nom'] ?? '');
    $prenom   = trim($_POST['prenom'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $telephone = trim($_POST['tel'] ?? '');

    if ($nom === '' || $prenom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        header('Location: register.php?error=invalid');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        header('Location: register.php?error=exists');
        exit;
    }

    $photo_profil = null;
    try {
        $photo_profil = handle_image_upload($_FILES['photo_profil'] ?? [], '', 'profil');
    } catch (RuntimeException $e) {
        header('Location: register.php?error=upload');
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO users (nom, prenom, email, password, telephone, photo_profil)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        htmlspecialchars($nom),
        htmlspecialchars($prenom),
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        htmlspecialchars($telephone),
        $photo_profil,
    ]);

    header('Location: login.php?register=success');
    exit;
}

// ---------------------------------------------------------------------------
// CONNEXION
// ---------------------------------------------------------------------------
if (isset($_POST['login'])) {
    csrf_check();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['password'] && password_verify($password, $user['password'])) {
        // Empêche la fixation de session
        session_regenerate_id(true);

        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['nom']        = $user['nom'];
        $_SESSION['prenom']     = $user['prenom'];
        $_SESSION['photo_profil'] = $user['photo_profil'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['user_role']  = $user['role']; // compatibilité avec les pages admin

        header('Location: profil.php');
        exit;
    }

    header('Location: login.php?error=1');
    exit;
}

// ---------------------------------------------------------------------------
// DÉCONNEXION
// ---------------------------------------------------------------------------
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

// ---------------------------------------------------------------------------
// DEMANDE DE RÉINITIALISATION
// ---------------------------------------------------------------------------
if (isset($_POST['reset_request'])) {
    csrf_check();

    $email = strtolower(trim($_POST['email'] ?? ''));

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $stmt->execute([$token, $expires, $email]);

        $reset_link = app_base_url() . "/reset_password.php?token=$token";

        $mailSent = false;
        if (SMTP_USER !== '' && SMTP_PASS !== '') {
            require __DIR__ . '/PHPMailer/src/Exception.php';
            require __DIR__ . '/PHPMailer/src/PHPMailer.php';
            require __DIR__ . '/PHPMailer/src/SMTP.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = SMTP_PORT;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Réinitialisation de votre mot de passe';
                $mail->Body    = "
                    <h1>Réinitialisation de mot de passe</h1>
                    <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
                    <p><a href='" . htmlspecialchars($reset_link) . "'>" . htmlspecialchars($reset_link) . "</a></p>
                    <p>Ce lien expirera dans 1 heure.</p>
                ";
                $mail->send();
                $mailSent = true;
            } catch (Exception $e) {
                error_log('Erreur PHPMailer: ' . $mail->ErrorInfo);
            }
        } else {
            // SMTP non configuré (variables d'environnement SMTP_USER/SMTP_PASS absentes).
            // En développement, le lien est journalisé pour permettre le test.
            error_log("SMTP non configuré — lien de réinitialisation : $reset_link");
            $_SESSION['reset_link'] = $reset_link; // affiché par reset_request_sent.php (développement uniquement)
        }

        // Réponse volontairement générique : ne pas révéler si l'email existe (anti-énumération)
        header('Location: reset_request_sent.php');
        exit;
    }

    // Email inconnu : même réponse que si l'email existait (anti-énumération)
    header('Location: reset_request_sent.php');
    exit;
}

// ---------------------------------------------------------------------------
// RÉINITIALISATION DU MOT DE PASSE
// ---------------------------------------------------------------------------
if (isset($_POST['reset_password'])) {
    csrf_check();

    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';

    if (strlen($password) < 8) {
        header('Location: reset_password.php?token=' . urlencode($token) . '&error=weak_password');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);

        header('Location: login.php?reset=success');
        exit;
    }

    header('Location: login.php?error=invalid_token');
    exit;
}

// Aucune action reconnue
header('Location: login.php');
exit;

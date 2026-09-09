<?php
/**
 * Contrôleur Auth : inscription, connexion (jeton Bearer), déconnexion,
 * profil courant, réinitialisation de mot de passe.
 */

function auth_register(array $params): void
{
    $nom       = input_str('nom');
    $prenom    = input_str('prenom');
    $email     = validate_email(input_str('email'));
    $password  = validate_password(input_str('password'));
    $telephone = input_str('tel');

    if ($nom === '' || $prenom === '') {
        api_error('Le nom et le prénom sont obligatoires');
    }

    $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        api_error('Cet email est déjà utilisé', 409);
    }

    $photo = handle_image_upload($_FILES['photo_profil'] ?? [], '', 'profil');

    $stmt = db()->prepare(
        "INSERT INTO users (nom, prenom, email, password, telephone, photo_profil)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        htmlspecialchars($nom),
        htmlspecialchars($prenom),
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        htmlspecialchars($telephone),
        $photo,
    ]);

    $userId = (int) db()->lastInsertId();
    $token  = issue_token($userId);

    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    json_success(['token' => $token, 'user' => user_public($user)], 201);
}

function auth_login(array $params): void
{
    $email    = validate_email(input_str('email'));
    $password = input_str('password');

    $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['password'] || !password_verify($password, $user['password'])) {
        api_error('Email ou mot de passe incorrect', 401);
    }

    $token = issue_token((int) $user['id']);
    json_success(['token' => $token, 'user' => user_public($user)]);
}

function auth_logout(array $params): void
{
    $user = require_auth();
    $token = bearer_token();
    db()->prepare("DELETE FROM api_tokens WHERE token_hash = ?")
        ->execute([hash('sha256', $token)]);
    json_success(['message' => 'Déconnecté']);
}

function auth_me(array $params): void
{
    $user = require_auth();

    // Indicateur transporteur (fiche existante)
    $stmt = db()->prepare("SELECT id, solde FROM transporteurs WHERE user_id = ?");
    $stmt->execute([(int) $user['id']]);
    $transporteur = $stmt->fetch(PDO::FETCH_ASSOC);

    $data = user_public($user);
    $data['is_transporteur'] = (bool) $transporteur;
    $data['solde'] = $transporteur ? (float) $transporteur['solde'] : 0.0;
    json_success($data);
}

function auth_reset_request(array $params): void
{
    $email = validate_email(input_str('email'));

    $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        db()->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")
            ->execute([$token, $expires, $user['id']]);

        $reset_link = FRONTEND_URL . '/reset-password.html?token=' . $token;

        if (SMTP_USER !== '' && SMTP_PASS !== '') {
            $sent = send_reset_email($email, $reset_link);
            if (!$sent) {
                error_log("Échec envoi email reset pour $email");
            }
        } else {
            // Mode développement : lien journalisé (et renvoyé pour faciliter les tests)
            error_log("SMTP non configuré — lien de réinitialisation : $reset_link");
            json_success([
                'message' => 'Si un compte existe avec cette adresse, un lien de réinitialisation vient d\'être envoyé.',
                'dev_reset_link' => $reset_link,
            ]);
        }
    }

    // Réponse générique (anti-énumération)
    json_success([
        'message' => 'Si un compte existe avec cette adresse, un lien de réinitialisation vient d\'être envoyé.',
    ]);
}

function auth_reset_password(array $params): void
{
    $token    = input_str('token');
    $password = validate_password(input_str('password'));

    $stmt = db()->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        api_error('Lien de réinitialisation invalide ou expiré', 400);
    }

    db()->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")
        ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);

    json_success(['message' => 'Mot de passe réinitialisé, vous pouvez vous connecter.']);
}

/** Envoi de l'email de réinitialisation via PHPMailer (si disponible). */
function send_reset_email(string $to, string $link): bool
{
    $phpmailer = dirname(__DIR__, 2) . '/../PHPMailer/src/PHPMailer.php';
    if (!is_file($phpmailer)) {
        error_log('PHPMailer introuvable, email non envoyé');
        return false;
    }
    require_once dirname(__DIR__, 2) . '/../PHPMailer/src/Exception.php';
    require_once $phpmailer;
    require_once dirname(__DIR__, 2) . '/../PHPMailer/src/SMTP.php';

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
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Réinitialisation de votre mot de passe';
        $safe = htmlspecialchars($link);
        $mail->Body = "<h1>Réinitialisation de mot de passe</h1>"
            . "<p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>"
            . "<p><a href='$safe'>$safe</a></p>"
            . "<p>Ce lien expirera dans 1 heure.</p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erreur PHPMailer : ' . $e->getMessage());
        return false;
    }
}

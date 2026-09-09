<?php
/**
 * Contrôleur Contact : formulaire de contact public + réponses admin.
 */

function contact_send(array $params): void
{
    $nom     = input_str('nom');
    $email   = validate_email(input_str('email'));
    $message = input_str('message');

    if ($nom === '' || $message === '') {
        api_error('Tous les champs sont obligatoires');
    }
    if (mb_strlen($message) > 5000) {
        api_error('Message trop long (5000 caractères max)');
    }

    db()->prepare("INSERT INTO messages_contact (nom, email, message) VALUES (?, ?, ?)")
        ->execute([htmlspecialchars($nom), $email, $message]);

    json_success(['message' => 'Votre message a bien été envoyé à l\'agence'], 201);
}

/** Réponses de l'admin aux messages de contact de l'utilisateur connecté. */
function contact_reponses(array $params): void
{
    $user = require_auth();
    $email = $user['email'];

    $stmt = db()->prepare(
        "SELECT id, nom, email, message, reponse, date_envoi, date_reponse
         FROM messages_contact
         WHERE email = ? AND reponse IS NOT NULL
         ORDER BY date_reponse DESC"
    );
    $stmt->execute([$email]);
    $reponses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db()->prepare("UPDATE messages_contact SET lu_par_utilisateur = 1 WHERE email = ? AND reponse IS NOT NULL")
        ->execute([$email]);

    json_success($reponses);
}

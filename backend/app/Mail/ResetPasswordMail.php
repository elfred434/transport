<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de réinitialisation de mot de passe.
 *
 * Même contenu que l'envoi PHPMailer de l'API d'origine. L'expéditeur et le
 * transport SMTP sont lus depuis SMTP_* (config/mail.php), jamais codés en dur.
 */
class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipient,
        public readonly string $link,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->recipient],
            subject: 'Réinitialisation de votre mot de passe',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reset-password');
    }
}

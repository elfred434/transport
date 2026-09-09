<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Erreur métier de l'API : message lisible + code HTTP.
 * Rendue globalement en {"success": false, "error": "..."} (voir bootstrap/app.php).
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 400,
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public static function unauthorized(string $message = 'Authentification requise'): self
    {
        return new self($message, 401);
    }

    public static function forbidden(string $message = 'Accès refusé'): self
    {
        return new self($message, 403);
    }

    public static function notFound(string $message = 'Ressource introuvable'): self
    {
        return new self($message, 404);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }
}

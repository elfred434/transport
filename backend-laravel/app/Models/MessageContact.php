<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Message du formulaire de contact public. Table `messages_contact` existante.
 *
 * Aucune authentification à l'envoi : l'expéditeur est identifié par son email,
 * qui sert aussi à lui rattacher les réponses de l'administration.
 */
class MessageContact extends Model
{
    public const CREATED_AT = 'date_envoi';
    public const UPDATED_AT = null;

    protected $table = 'messages_contact';

    protected $fillable = [
        'nom', 'email', 'message', 'reponse', 'date_reponse',
        'lu_par_admin', 'lu_par_utilisateur',
    ];

    protected $casts = [
        'date_envoi' => 'datetime',
        'date_reponse' => 'datetime',
        'lu_par_admin' => 'boolean',
        'lu_par_utilisateur' => 'boolean',
    ];

    public function aRecuUneReponse(): bool
    {
        return $this->reponse !== null;
    }
}

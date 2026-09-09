<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message utilisateur <-> administrateur. Table `messages_admin` existante.
 *
 * Canal de support distinct de la messagerie entre utilisateurs : un
 * utilisateur n'y choisit pas son destinataire (toujours l'administration),
 * tandis qu'un administrateur précise l'utilisateur visé.
 */
class MessageAdmin extends Model
{
    public const CREATED_AT = 'date_envoi';
    public const UPDATED_AT = null;

    protected $table = 'messages_admin';

    protected $fillable = ['expediteur_id', 'destinataire_id', 'contenu', 'lu'];

    protected $casts = [
        'date_envoi' => 'datetime',
        'lu' => 'boolean',
    ];

    public function expediteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expediteur_id');
    }

    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }
}

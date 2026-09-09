<?php

namespace App\Models;

use App\Support\Files;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message utilisateur <-> utilisateur. Table `messages` existante.
 *
 * Peut porter un contexte métier (colis_id / voyage_id) et une pièce jointe
 * image. Les messages reçus sont marqués lus à la lecture de la conversation.
 */
class Message extends Model
{
    public const CREATED_AT = 'date_envoi';
    public const UPDATED_AT = null;

    protected $table = 'messages';

    protected $fillable = [
        'expediteur_id', 'destinataire_id', 'colis_id', 'voyage_id',
        'contenu', 'fichier', 'lu',
    ];

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

    public function toPublicArray(): array
    {
        $data = $this->attributesToArray();
        $data['fichier_url'] = Files::url($this->fichier);
        unset($data['fichier']);
        $data['date_envoi'] = $this->date_envoi?->toDateTimeString();

        return $data;
    }
}

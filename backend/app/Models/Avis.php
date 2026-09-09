<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis laissé par un utilisateur sur un transporteur. Table `avis` existante.
 *
 * Un seul avis par couple (utilisateur, transporteur) — contrainte applicative.
 * Seuls les avis `approuve` sont visibles publiquement.
 */
class Avis extends Model
{
    public const CREATED_AT = 'date_avis';
    public const UPDATED_AT = null;

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_APPROUVE = 'approuve';
    public const STATUT_REFUSE = 'refuse';

    protected $table = 'avis';

    protected $fillable = [
        'transporteur_id', 'user_id', 'colis_id', 'note', 'commentaire', 'statut',
    ];

    protected $casts = [
        'note' => 'integer',
        'date_avis' => 'datetime',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** `transporteur_id` référence users.id (et non transporteurs.id). */
    public function transporteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transporteur_id');
    }

    public function colis(): BelongsTo
    {
        return $this->belongsTo(Colis::class, 'colis_id');
    }

    public function scopeApprouves($query)
    {
        return $query->where('statut', self::STATUT_APPROUVE);
    }
}

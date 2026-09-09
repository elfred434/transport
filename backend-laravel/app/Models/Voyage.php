<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Voyage proposé par un transporteur. Table `voyages` existante.
 *
 * Un voyage n'est visible des expéditeurs qu'une fois `approuve` par un
 * administrateur, et seulement tant que sa date de départ n'est pas passée.
 */
class Voyage extends Model
{
    public const CREATED_AT = 'date_post';
    public const UPDATED_AT = null;

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_APPROUVE = 'approuve';
    public const STATUT_REFUSE = 'refuse';

    protected $table = 'voyages';

    protected $fillable = [
        'user_id', 'pays_depart', 'pays_destination', 'date_depart',
        'heure_depart', 'poids_max', 'email', 'telephone', 'statut',
    ];

    protected $casts = [
        'date_depart' => 'date',
        'poids_max' => 'float',
        'date_post' => 'datetime',
    ];

    public function transporteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'voyage_id');
    }

    /**
     * Voyages susceptibles d'acheminer un colis donné.
     *
     * Reprend la règle de mise en relation historique : destination identique
     * au pays du colis, capacité suffisante, départ à venir, voyage approuvé.
     */
    public function scopeCompatiblesAvec($query, Colis $colis)
    {
        return $query->where('pays_destination', $colis->pays)
            ->where('poids_max', '>=', $colis->poids)
            ->whereDate('date_depart', '>=', now()->toDateString())
            ->where('statut', self::STATUT_APPROUVE)
            ->orderBy('date_depart');
    }
}

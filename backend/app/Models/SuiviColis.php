<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Étape de suivi d'un colis. Table `suivi_colis` existante.
 *
 * Une étape est historisée (jamais modifiée) : le statut courant d'un colis est
 * celui de sa dernière étape par `date_etape`.
 *
 * Un « Livré » posé par un transporteur crée une demande de confirmation
 * (`demande_livraison = 1`) qu'un administrateur confirme
 * (`confirme_par_admin = 1`) ou refuse (suppression de l'étape).
 */
class SuiviColis extends Model
{
    public const CREATED_AT = 'date_etape';
    public const UPDATED_AT = null;

    public const STATUT_EN_ATTENTE = 'En attente';
    public const STATUT_EN_COURS = 'En cours';
    public const STATUT_LIVRE = 'Livré';

    protected $table = 'suivi_colis';

    protected $fillable = ['colis_id', 'statut', 'confirme_par_admin', 'demande_livraison'];

    protected $casts = [
        'date_etape' => 'datetime',
        'confirme_par_admin' => 'boolean',
        'demande_livraison' => 'boolean',
    ];

    public function colis(): BelongsTo
    {
        return $this->belongsTo(Colis::class, 'colis_id');
    }

    /**
     * Étapes visibles publiquement.
     *
     * Un « Livré » non confirmé par l'administration reste masqué : il ne
     * s'agit encore que d'une demande en attente de validation.
     */
    public function scopeVisibles($query)
    {
        return $query->where(function ($q) {
            $q->where('confirme_par_admin', true)
              ->orWhere('statut', '!=', self::STATUT_LIVRE);
        });
    }

    /** Demandes de livraison en attente de décision d'un administrateur. */
    public function scopeDemandesEnAttente($query)
    {
        return $query->where('demande_livraison', true)
            ->where('confirme_par_admin', false)
            ->where('statut', self::STATUT_LIVRE);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paiement d'un colis. Table `paiements` existante.
 *
 * Le paiement est simulé : aucune donnée sensible n'est persistée, uniquement
 * un numéro masqué et l'opérateur dans `details_paiement` (JSON).
 */
class Paiement extends Model
{
    public const CREATED_AT = 'date_creation';
    public const UPDATED_AT = null;

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_PAYE = 'paye';
    public const STATUT_ECHEC = 'echec';
    public const STATUT_ANNULE = 'annule';

    public const METHODE_CARTE = 'carte_credit';
    public const METHODE_MOBILE = 'mobile_money';
    public const METHODE_KKIAPAY = 'kkiapay';
    public const METHODE_AUTRE = 'autre';
    public const METHODE_VIREMENT = 'virement';

    protected $table = 'paiements';

    protected $fillable = [
        'user_id', 'colis_id', 'montant', 'methode_paiement', 'numero_transaction',
        'operateur', 'reference', 'details_paiement', 'statut', 'date_paiement',
        'ip_client', 'device_info',
    ];

    protected $casts = [
        'montant' => 'float',
        'details_paiement' => 'array',
        'date_creation' => 'datetime',
        'date_paiement' => 'datetime',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function colis(): BelongsTo
    {
        return $this->belongsTo(Colis::class, 'colis_id');
    }

    public function estPaye(): bool
    {
        return $this->statut === self::STATUT_PAYE;
    }
}

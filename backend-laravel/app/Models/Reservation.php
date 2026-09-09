<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réservation d'un colis sur un voyage. Table `reservations` existante.
 *
 * Cycle : un transporteur réserve un colis approuvé pour l'un de ses voyages
 * (`en_attente`), puis le PROPRIÉTAIRE du colis accepte ou refuse. Une
 * réservation `accepte` devient `termine` à la confirmation de livraison.
 */
class Reservation extends Model
{
    public const CREATED_AT = 'date_reservation';
    public const UPDATED_AT = null;

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACCEPTE = 'accepte';
    public const STATUT_REFUSE = 'refuse';
    public const STATUT_TERMINE = 'termine';

    protected $table = 'reservations';

    protected $fillable = ['colis_id', 'voyage_id', 'statut'];

    protected $casts = [
        'date_reservation' => 'datetime',
    ];

    public function colis(): BelongsTo
    {
        return $this->belongsTo(Colis::class, 'colis_id');
    }

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class, 'voyage_id');
    }
}

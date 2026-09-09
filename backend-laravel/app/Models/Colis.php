<?php

namespace App\Models;

use App\Support\Files;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Colis à transporter. Table `colis` existante.
 *
 * Horodatage : `date_post` (défaut MySQL), pas de colonne `updated_at`.
 */
class Colis extends Model
{
    public const CREATED_AT = 'date_post';
    public const UPDATED_AT = null;

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_APPROUVE = 'approuve';
    public const STATUT_REFUSE = 'refuse';

    protected $table = 'colis';

    protected $fillable = [
        'user_id', 'nom_colis', 'image_colis', 'type_produit', 'nombre_produits',
        'poids', 'dimensions', 'pays', 'ville', 'date_limite', 'adresse_depart',
        'adresse_destination', 'prix_estime', 'numero_suivi', 'statut',
    ];

    protected $casts = [
        'poids' => 'float',
        'prix_estime' => 'float',
        'nombre_produits' => 'integer',
        'date_limite' => 'date',
        'date_post' => 'datetime',
    ];

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'colis_id');
    }

    public function etapesSuivi(): HasMany
    {
        return $this->hasMany(SuiviColis::class, 'colis_id')->orderBy('date_etape');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class, 'colis_id');
    }

    /** Dernière étape de suivi, ou null. */
    public function derniereEtape(): ?SuiviColis
    {
        return $this->etapesSuivi()->orderByDesc('date_etape')->first();
    }

    /**
     * Sérialisation publique — même contrat que `colis_public()` de l'API
     * d'origine : `image_colis` est retiré au profit de `image_url`.
     */
    public function toPublicArray(): array
    {
        $data = $this->attributesToArray();

        // Respecter les formats de l'API d'origine pour les dates.
        if (isset($data['date_post'])) {
            $data['date_post'] = $this->date_post?->toDateTimeString();
        }
        if (isset($data['date_limite'])) {
            $data['date_limite'] = $this->date_limite?->toDateString();
        }

        $data['image_url'] = Files::url($this->image_colis);
        unset($data['image_colis']);

        return $data;
    }
}

<?php

namespace App\Models;

use App\Support\Files;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fiche transporteur. Table `transporteurs` existante.
 *
 * Créée lors de la première proposition de voyage. Le `solde` est crédité de la
 * commission (5 % du prix estimé) à la confirmation de chaque livraison.
 */
class Transporteur extends Model
{
    public const CREATED_AT = 'date_creation';
    public const UPDATED_AT = null;

    protected $table = 'transporteurs';

    protected $fillable = [
        'user_id', 'numero_permis', 'vehicule', 'compagnie', 'adresse',
        'ville', 'pays', 'photo_vehicule', 'solde',
    ];

    protected $casts = [
        'solde' => 'float',
        'date_creation' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function toPublicArray(): array
    {
        $data = $this->attributesToArray();
        $data['photo_vehicule_url'] = Files::url($this->photo_vehicule);
        unset($data['photo_vehicule']);

        if (isset($data['date_creation'])) {
            $data['date_creation'] = $this->date_creation?->toDateTimeString();
        }

        return $data;
    }
}

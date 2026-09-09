<?php

namespace App\Models;

use App\Support\Files;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Utilisateur de la plateforme.
 *
 * Branché sur la table `users` existante (héritée du schéma d'origine) :
 * aucune migration Laravel ne recrée cette table.
 *
 * Particularités du schéma :
 *  - pas de `created_at`/`updated_at` : la colonne d'horodatage est
 *    `date_inscription` (définie par défaut côté MySQL) ;
 *  - pas de `remember_token` : l'API est sans état (jetons Sanctum) ;
 *  - `role` est un ENUM('utilisateur','transporteur','admin').
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const CREATED_AT = 'date_inscription';
    public const UPDATED_AT = null;

    public const ROLE_UTILISATEUR = 'utilisateur';
    public const ROLE_TRANSPORTEUR = 'transporteur';
    public const ROLE_ADMIN = 'admin';

    protected $table = 'users';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'telephone',
        'photo_profil',
        'role',
    ];

    protected $hidden = [
        'password',
        'reset_token',
        'reset_expires',
    ];

    protected function casts(): array
    {
        return [
            'date_inscription' => 'datetime',
            'reset_expires' => 'datetime',
            // Le cast 'hashed' hache toute valeur assignée et ignore une valeur
            // déjà hachée (idempotent) : aucun mot de passe ne peut être persisté
            // en clair, y compris par un futur appelant qui oublierait Hash::make.
            'password' => 'hashed',
        ];
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function transporteur(): HasOne
    {
        return $this->hasOne(Transporteur::class, 'user_id');
    }

    public function colis(): HasMany
    {
        return $this->hasMany(Colis::class, 'user_id');
    }

    public function voyages(): HasMany
    {
        return $this->hasMany(Voyage::class, 'user_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class, 'user_id');
    }

    // ------------------------------------------------------------------
    // Helpers métier
    // ------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTransporteur(): bool
    {
        return $this->transporteur()->exists();
    }

    /** Représentation publique (même contrat que l'API actuelle). */
    public function toPublicArray(): array
    {
        return [
            'id' => (int) $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'photo_url' => Files::url($this->photo_profil),
            'role' => $this->role,
            'date_inscription' => $this->date_inscription?->toDateTimeString(),
        ];
    }
}

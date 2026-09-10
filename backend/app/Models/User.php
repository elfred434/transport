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
 * Utilisateur de la plateforme avec hiérarchie :
 * - client (ex utilisateur)
 * - transporteur
 * - admin (admin simple)
 * - super_admin (full)
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const CREATED_AT = 'date_inscription';
    public const UPDATED_AT = null;

    // Ancien alias pour compatibilité
    public const ROLE_UTILISATEUR = 'utilisateur';
    public const ROLE_CLIENT = 'client';
    public const ROLE_TRANSPORTEUR = 'transporteur';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLES = [
        self::ROLE_CLIENT,
        self::ROLE_UTILISATEUR, // alias
        self::ROLE_TRANSPORTEUR,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    public const ROLES_ASSIGNABLE_BY_ADMIN = [
        self::ROLE_CLIENT,
        self::ROLE_TRANSPORTEUR,
    ];

    public const ROLES_ASSIGNABLE_BY_SUPER_ADMIN = [
        self::ROLE_CLIENT,
        self::ROLE_TRANSPORTEUR,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    protected $table = 'users';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'telephone',
        'photo_profil',
        'role',
        'google_id',
        'provider',
        'avatar',
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
            'password' => 'hashed',
        ];
    }

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

    // Helpers
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function isAdminSimple(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isClient(): bool
    {
        return in_array($this->role, [self::ROLE_CLIENT, self::ROLE_UTILISATEUR], true);
    }

    public function isTransporteur(): bool
    {
        return $this->transporteur()->exists();
    }

    public function hasRole(string $role): bool
    {
        if ($role === self::ROLE_CLIENT) {
            return $this->isClient();
        }
        return $this->role === $role;
    }

    public function canManageRole(string $targetRole): bool
    {
        if ($this->isSuperAdmin()) {
            return in_array($targetRole, self::ROLES_ASSIGNABLE_BY_SUPER_ADMIN, true);
        }
        if ($this->isAdminSimple()) {
            return in_array($targetRole, self::ROLES_ASSIGNABLE_BY_ADMIN, true);
        }
        return false;
    }

    public function toPublicArray(): array
    {
        return [
            'id' => (int) $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'photo_url' => Files::url($this->photo_profil),
            'role' => $this->role === self::ROLE_UTILISATEUR ? self::ROLE_CLIENT : $this->role,
            'date_inscription' => $this->date_inscription?->toDateTimeString(),
        ];
    }
}

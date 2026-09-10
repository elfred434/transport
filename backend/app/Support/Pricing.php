<?php

namespace App\Support;

/**
 * Règles métier de tarification.
 * Historique: 5% transporteur, 95% plateforme (erreur) -> corrigé: 95% transporteur, 5% admin
 */
final class Pricing
{
    /** Taux commission transporteur (95% du prix estimé) */
    public const COMMISSION_TRANSPORTEUR_RATE = 0.95;

    /** Taux commission admin/plateforme (5%) */
    public const COMMISSION_ADMIN_RATE = 0.05;

    /** Ancien taux pour compatibilité */
    public const COMMISSION_RATE = 0.05;

    /**
     * Prix estimé d'un colis.
     * Règle historique : 1 000 F de base + 1 000 F par kg, plancher à 1 000 F,
     * puis majoration de 20 %. Arrondi à 2 décimales.
     */
    public static function forWeight(float $poids): float
    {
        return round(max(1000.0, 1000.0 + (1000.0 * $poids)) * 1.2, 2);
    }

    /** Commission transporteur (95%) */
    public static function commission(float $prixEstime): float
    {
        return self::commissionTransporteur($prixEstime);
    }

    public static function commissionTransporteur(float $prixEstime): float
    {
        return round($prixEstime * self::COMMISSION_TRANSPORTEUR_RATE, 2);
    }

    public static function commissionAdmin(float $prixEstime): float
    {
        return round($prixEstime * self::COMMISSION_ADMIN_RATE, 2);
    }
}

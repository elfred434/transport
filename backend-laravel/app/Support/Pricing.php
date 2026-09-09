<?php

namespace App\Support;

/**
 * Règles métier de tarification — identiques à l'implémentation d'origine.
 */
final class Pricing
{
    /** Taux de commission du transporteur sur un colis livré et confirmé. */
    public const COMMISSION_RATE = 0.05;

    /**
     * Prix estimé d'un colis.
     *
     * Règle historique : 1 000 F de base + 1 000 F par kg, plancher à 1 000 F,
     * puis majoration de 20 %. Arrondi à 2 décimales.
     */
    public static function forWeight(float $poids): float
    {
        return round(max(1000.0, 1000.0 + (1000.0 * $poids)) * 1.2, 2);
    }

    /** Commission versée au transporteur lors de la confirmation de livraison. */
    public static function commission(float $prixEstime): float
    {
        return round($prixEstime * self::COMMISSION_RATE, 2);
    }
}

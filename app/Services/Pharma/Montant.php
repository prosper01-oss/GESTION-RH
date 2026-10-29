<?php

namespace App\Services\Pharma;

class Montant
{
    /** Formate un montant en Ariary avec séparateurs de milliers. */
    public static function format(float|string|null $montant): string
    {
        return number_format((float) $montant, 2, '.', ' ');
    }

    /**
     * Arrondit la monnaie au multiple de 100 le plus proche (règle de la caisse) :
     * reste ≤ 50 → centaine inférieure (130 → 100), reste > 50 → centaine supérieure (160 → 200).
     */
    public static function arrondirCentaine(float $montant): float
    {
        if ($montant <= 0) {
            return 0.0;
        }

        $centaines = (int) floor($montant / 100);
        $reste = $montant - $centaines * 100;

        return $reste > 50 ? ($centaines + 1) * 100 : $centaines * 100;
    }
}

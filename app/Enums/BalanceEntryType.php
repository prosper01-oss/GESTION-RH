<?php

namespace App\Enums;

/**
 * Types de mouvements du registre des soldes (section 8.3 du cahier des charges).
 * Le signe du nombre de jours porte l'effet : positif ajoute, négatif retire.
 */
enum BalanceEntryType: string
{
    case Acquisition = 'acquisition';
    case Report = 'report';
    case Reservation = 'reservation';
    case Consommation = 'consommation';
    case AnnulationReservation = 'annulation_reservation';
    case Correction = 'correction';
    case Peremption = 'peremption';

    public function label(): string
    {
        return match ($this) {
            self::Acquisition => 'Acquisition',
            self::Report => 'Report',
            self::Reservation => 'Réservation',
            self::Consommation => 'Consommation',
            self::AnnulationReservation => 'Annulation de réservation',
            self::Correction => 'Correction',
            self::Peremption => 'Péremption',
        };
    }
}

<?php

namespace App\Enums;

/**
 * Statuts communs aux trois types de demandes (section 3.2 du cahier des charges).
 */
enum RequestStatus: string
{
    case Brouillon = 'brouillon';
    case Soumise = 'soumise';
    case ACorriger = 'a_corriger';
    case TransmiseMedecin = 'transmise_medecin';
    case RenvoyeeRh = 'renvoyee_rh';
    case Approuvee = 'approuvee';
    case Refusee = 'refusee';
    case Finalisee = 'finalisee';
    case Terminee = 'terminee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Soumise => 'Soumise',
            self::ACorriger => 'À corriger',
            self::TransmiseMedecin => 'Transmise au Médecin-Chef',
            self::RenvoyeeRh => 'Renvoyée aux RH',
            self::Approuvee => 'Approuvée',
            self::Refusee => 'Refusée',
            self::Finalisee => 'Finalisée',
            self::Terminee => 'Terminée',
            self::Annulee => 'Annulée',
        };
    }

    /**
     * Demandes en cours d'instruction : leurs jours sont déduits du solde demandable (section 4.3).
     *
     * @return list<self>
     */
    public static function pending(): array
    {
        return [self::Soumise, self::ACorriger, self::TransmiseMedecin, self::RenvoyeeRh];
    }

    /**
     * Demandes qui occupent la période de l'agent (contrôle des chevauchements).
     *
     * @return list<self>
     */
    public static function occupyingPeriod(): array
    {
        return [...self::pending(), self::Approuvee, self::Finalisee, self::Terminee];
    }

    /**
     * L'agent peut modifier sa demande dans ces statuts.
     */
    public function isEditableByOwner(): bool
    {
        return in_array($this, [self::Brouillon, self::ACorriger], true);
    }
}

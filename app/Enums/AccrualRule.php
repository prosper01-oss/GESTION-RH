<?php

namespace App\Enums;

enum AccrualRule: string
{
    /** Droits acquis chaque mois de service (droit annuel / 12). */
    case Mensuelle = 'monthly';
    /** Droit annuel crédité en une fois au 1er janvier. */
    case Annuelle = 'annual';
    /** Pas de droit acquis : congé accordé au cas par cas (maladie, maternité…). */
    case Aucune = 'none';
}

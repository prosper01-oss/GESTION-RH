<?php

namespace App\Enums;

/**
 * Liste provisoire, à confirmer avec les RH de l'OMIT.
 */
enum ContractType: string
{
    case Cdi = 'cdi';
    case Cdd = 'cdd';
    case Stage = 'stage';
    case Autre = 'autre';
}

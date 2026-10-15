<?php

namespace App\Enums;

enum SituationFamiliale: string
{
    case Celibataire = 'celibataire';
    case Marie = 'marie';
    case Divorce = 'divorce';
    case Veuf = 'veuf';
}

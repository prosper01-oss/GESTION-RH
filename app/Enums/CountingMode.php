<?php

namespace App\Enums;

enum CountingMode: string
{
    /** Tous les jours de la période sont décomptés. */
    case Calendaire = 'calendar';
    /** Seuls les jours ouvrés sont décomptés (hors jours non ouvrés et jours fériés). */
    case Ouvre = 'working';
}

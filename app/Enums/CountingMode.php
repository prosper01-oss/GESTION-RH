<?php

namespace App\Enums;

enum CountingMode: string
{
    /** Tous les jours de la période sont décomptés. */
    case Calendaire = 'calendar';
    /** Tous les jours de la période, sauf les jours fériés (règle R3 de l'OMIT). */
    case CalendaireHorsFeries = 'calendar_excluding_holidays';
    /** Seuls les jours ouvrés sont décomptés (hors jours non ouvrés et jours fériés). */
    case Ouvre = 'working';
}

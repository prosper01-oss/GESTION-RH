<?php

use Illuminate\Support\Facades\Schedule;

// Droits du mois écoulé, crédités le 1er de chaque mois.
Schedule::command('omit:accrue-leave')->monthlyOn(1, '01:00')->withoutOverlapping();

// Clôture de l'année écoulée : reports, péremptions et droits annuels de la nouvelle année.
Schedule::command('omit:close-leave-year')->yearlyOn(1, 1, '02:00')->withoutOverlapping();

// Congés finalisés dont la période est passée : consommation définitive du solde.
Schedule::command('omit:close-finished-requests')->dailyAt('00:30')->withoutOverlapping();

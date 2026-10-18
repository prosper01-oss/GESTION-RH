<?php

use Illuminate\Support\Facades\Schedule;

// Droits du mois écoulé, crédités le 1er de chaque mois.
Schedule::command('omit:accrue-leave')->monthlyOn(1, '01:00')->withoutOverlapping();

// Clôture de l'année écoulée : reports, péremptions et droits annuels de la nouvelle année.
Schedule::command('omit:close-leave-year')->yearlyOn(1, 1, '02:00')->withoutOverlapping();

// Rappel annuel du solde de congés : chaque agent reçoit son récapitulatif le 5 janvier.
Schedule::command('omit:remind-leave-balance')->yearlyOn(1, 5, '08:00')->withoutOverlapping();

// Congés finalisés dont la période est passée : consommation définitive du solde.
Schedule::command('omit:close-finished-requests')->dailyAt('00:30')->withoutOverlapping();

// Rappel des demandes en attente depuis plus de OMIT_REMINDER_DAYS jours.
Schedule::command('omit:remind-pending-requests')->weekdays()->at('07:30')->withoutOverlapping();

// Sauvegarde quotidienne de la base et des fichiers privés (section 5).
Schedule::command('omit:backup')->dailyAt('23:30')->withoutOverlapping();

// R6 : justificatifs attendus et non fournis dans le délai (congé maladie : 48 h).
Schedule::command('omit:check-missing-attachments')->hourly()->withoutOverlapping();

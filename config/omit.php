<?php

use App\Services\Sms\LogSmsGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Jours non ouvrés
    |--------------------------------------------------------------------------
    |
    | Jours de la semaine exclus du décompte en jours ouvrés (0 = dimanche,
    | 6 = samedi). À confirmer avec le règlement intérieur de l'OMIT.
    |
    */

    'non_working_days' => array_map('intval', explode(',', env('OMIT_NON_WORKING_DAYS', '0,6'))),

    /*
    |--------------------------------------------------------------------------
    | Rappels et SMS
    |--------------------------------------------------------------------------
    |
    | reminder_days : une demande en attente depuis plus de ce nombre de jours
    | déclenche un rappel quotidien aux RH ou au Médecin-Chef (section 3.5).
    | sms_gateway : classe implémentant App\Contracts\SmsGateway.
    |
    */

    'reminder_days' => (int) env('OMIT_REMINDER_DAYS', 3),

    'sms_gateway' => env('OMIT_SMS_GATEWAY', LogSmsGateway::class),

];

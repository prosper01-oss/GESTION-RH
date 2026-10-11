<?php

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

];

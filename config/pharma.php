<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expiration des paniers de caisse
    |--------------------------------------------------------------------------
    |
    | Une ligne « Article » restée en panier au-delà de ce délai (en minutes)
    | est retirée et son stock réservé est restitué (omit:pharma-purger-paniers).
    |
    */

    'panier_expiration_minutes' => max(1, (int) env('PANIER_EXPIRATION_MINUTES', 60)),

];

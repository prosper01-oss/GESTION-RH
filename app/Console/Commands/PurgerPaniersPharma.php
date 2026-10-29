<?php

namespace App\Console\Commands;

use App\Services\Pharma\PanierExpiration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('omit:pharma-purger-paniers')]
#[Description('Pharmacie : restitue le stock des paniers non finalisés depuis plus de PANIER_EXPIRATION_MINUTES minutes')]
class PurgerPaniersPharma extends Command
{
    public function handle(): int
    {
        $purges = PanierExpiration::purger();

        $this->info("{$purges} ligne(s) de panier expirée(s) purgée(s), stock restitué.");

        return self::SUCCESS;
    }
}

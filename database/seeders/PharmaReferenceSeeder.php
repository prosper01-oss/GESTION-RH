<?php

namespace Database\Seeders;

use App\Models\Pharma\Pourcentage;
use App\Models\Pharma\Unite;
use Illuminate\Database\Seeder;

/**
 * Données de référence de la Pharmacie : unités de conditionnement et marge par défaut.
 * Peut être relancé sans risque.
 */
class PharmaReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['nomUnite' => 'Ut', 'nomComplet' => 'Ut', 'supun' => false],
            ['nomUnite' => 'BT', 'nomComplet' => 'Boîte', 'supun' => false],
            ['nomUnite' => 'Flacon', 'nomComplet' => 'Flacon', 'supun' => true],
            ['nomUnite' => 'Tube', 'nomComplet' => 'Tube', 'supun' => true],
            ['nomUnite' => 'Unité', 'nomComplet' => 'Unité', 'supun' => false],
        ] as $unite) {
            Unite::updateOrCreate(['nomUnite' => $unite['nomUnite']], $unite);
        }

        if (! Pourcentage::query()->exists()) {
            Pourcentage::create(['pourcentage' => 20]);
        }
    }
}

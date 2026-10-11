<?php

namespace Database\Seeders;

use App\Enums\AccrualRule;
use App\Enums\CountingMode;
use App\Models\LeaveType;
use App\Models\PermissionType;
use App\Models\PublicHoliday;
use Illuminate\Database\Seeder;

/**
 * Paramètres de départ, à faire valider par les RH (section 15 du cahier des charges).
 * Ne modifie pas un type ou un jour férié déjà présent.
 */
class LeaveSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'type' => ['code' => 'CA', 'name' => 'Congé annuel', 'is_paid' => true, 'has_balance' => true, 'allows_half_day' => true],
                // Hypothèse : 2,5 jours calendaires par mois de service (Code du travail, à vérifier).
                'policy' => ['accrual_rule' => AccrualRule::Mensuelle, 'annual_entitlement' => 30, 'counting_mode' => CountingMode::Calendaire, 'carry_over_max' => null],
            ],
            [
                'type' => ['code' => 'CM', 'name' => 'Congé de maladie', 'is_paid' => true, 'has_balance' => false, 'requires_attachment' => true, 'is_medical' => true],
                'policy' => ['accrual_rule' => AccrualRule::Aucune, 'annual_entitlement' => 0, 'counting_mode' => CountingMode::Calendaire, 'carry_over_max' => null],
            ],
            [
                'type' => ['code' => 'MAT', 'name' => 'Congé de maternité', 'is_paid' => true, 'has_balance' => false, 'requires_attachment' => true, 'is_medical' => true],
                'policy' => ['accrual_rule' => AccrualRule::Aucune, 'annual_entitlement' => 0, 'counting_mode' => CountingMode::Calendaire, 'carry_over_max' => null],
            ],
        ];

        foreach ($types as ['type' => $type, 'policy' => $policy]) {
            $leaveType = LeaveType::firstOrCreate(['code' => $type['code']], $type);

            if (! $leaveType->policies()->exists()) {
                $leaveType->policies()->create($policy + ['effective_from' => '2000-01-01']);
            }
        }

        // Catégories de permission indicatives, à remplacer par celles du règlement intérieur.
        $permissionTypes = [
            ['name' => 'Événement familial (mariage, naissance, décès)', 'is_paid' => true, 'requires_attachment' => true, 'max_duration_hours' => 72],
            ['name' => 'Rendez-vous médical', 'is_paid' => true, 'requires_attachment' => true, 'is_medical' => true, 'max_duration_hours' => 8],
            ['name' => 'Convenance personnelle', 'is_paid' => false, 'max_duration_hours' => 8],
        ];

        foreach ($permissionTypes as $permissionType) {
            PermissionType::firstOrCreate(['name' => $permissionType['name']], $permissionType);
        }

        // Fêtes légales à date fixe à Madagascar (à vérifier). Les fêtes mobiles
        // (lundi de Pâques, Ascension, lundi de Pentecôte) sont à saisir chaque année.
        $holidays = [
            '2000-01-01' => 'Jour de l\'An',
            '2000-03-08' => 'Journée internationale de la femme',
            '2000-03-29' => 'Commémoration des événements de 1947',
            '2000-05-01' => 'Fête du Travail',
            '2000-06-26' => "Fête de l'Indépendance",
            '2000-08-15' => 'Assomption',
            '2000-11-01' => 'Toussaint',
            '2000-12-25' => 'Noël',
        ];

        foreach ($holidays as $date => $label) {
            if (! PublicHoliday::query()->whereDate('date', $date)->exists()) {
                PublicHoliday::create(['date' => $date, 'label' => $label, 'is_recurring' => true]);
            }
        }
    }
}

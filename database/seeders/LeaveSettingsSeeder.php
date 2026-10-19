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
                // Règles OMIT : R1 2,5 jours par mois, R3 jours fériés non décomptés, R8 dépôt 15 jours à l'avance.
                'type' => ['code' => 'CA', 'name' => 'Congé annuel', 'is_paid' => true, 'has_balance' => true, 'allows_half_day' => true, 'min_notice_days' => 15],
                'policy' => ['accrual_rule' => AccrualRule::Mensuelle, 'annual_entitlement' => 30, 'counting_mode' => CountingMode::CalendaireHorsFeries, 'carry_over_max' => null],
            ],
            [
                // R6 : justificatif à fournir sous 48 h après le premier jour d'arrêt.
                'type' => ['code' => 'CM', 'name' => 'Congé de maladie', 'is_paid' => true, 'has_balance' => false, 'requires_attachment' => true, 'is_medical' => true, 'attachment_deadline_hours' => 48],
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
            ['name' => 'Autres (à préciser)', 'is_paid' => true, 'requires_details' => true],
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

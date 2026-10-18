<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Applique les règles de gestion validées par l'OMIT (R3, R6, R8) aux types de congés existants.
 * Sans effet sur un type absent. Le changement de décompte crée une nouvelle version des règles :
 * les congés déjà décomptés gardent l'ancienne.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $annualLeave = DB::table('leave_types')->where('code', 'CA')->first();

        if ($annualLeave) {
            DB::table('leave_types')->where('id', $annualLeave->id)->update(['min_notice_days' => 15, 'updated_at' => $now]);

            $current = DB::table('leave_policies')
                ->where('leave_type_id', $annualLeave->id)
                ->whereDate('effective_from', '<=', $now->toDateString())
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if ($current && $current->counting_mode !== 'calendar_excluding_holidays') {
                $sameDay = DB::table('leave_policies')
                    ->where('leave_type_id', $annualLeave->id)
                    ->whereDate('effective_from', $now->toDateString())
                    ->first();

                $attributes = [
                    'accrual_rule' => $current->accrual_rule,
                    'annual_entitlement' => $current->annual_entitlement,
                    'counting_mode' => 'calendar_excluding_holidays',
                    'carry_over_max' => $current->carry_over_max,
                    'updated_at' => $now,
                ];

                $sameDay
                    ? DB::table('leave_policies')->where('id', $sameDay->id)->update($attributes)
                    : DB::table('leave_policies')->insert($attributes + [
                        'leave_type_id' => $annualLeave->id,
                        'effective_from' => $now->toDateString(),
                        'created_at' => $now,
                    ]);
            }
        }

        DB::table('leave_types')->where('code', 'CM')->update(['attachment_deadline_hours' => 48, 'updated_at' => $now]);
    }

    /**
     * Les paramètres restent modifiables dans l'écran « Types de congés » ; le retour arrière
     * ne remet que les colonnes à leur valeur neutre, la version de règles est conservée.
     */
    public function down(): void
    {
        DB::table('leave_types')->where('code', 'CA')->update(['min_notice_days' => 0]);
        DB::table('leave_types')->where('code', 'CM')->update(['attachment_deadline_hours' => null]);
    }
};

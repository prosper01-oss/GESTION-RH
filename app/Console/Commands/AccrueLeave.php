<?php

namespace App\Console\Commands;

use App\Services\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('omit:accrue-leave
    {--month= : Mois à créditer (AAAA-MM), par défaut le mois précédent}
    {--until= : Dernier mois à créditer (AAAA-MM), pour rattraper une période depuis --month}')]
#[Description('Crédite les droits à congés acquis pour un ou plusieurs mois écoulés (sans effet sur un mois déjà crédité)')]
class AccrueLeave extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LeaveBalanceService $balances): int
    {
        foreach (['month', 'until'] as $option) {
            $value = $this->option($option);
            if ($value !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
                $this->error("--{$option} doit être au format AAAA-MM.");

                return self::FAILURE;
            }
        }

        $first = $this->option('month')
            ? CarbonImmutable::createFromFormat('!Y-m', $this->option('month'))
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $last = $this->option('until') ? CarbonImmutable::createFromFormat('!Y-m', $this->option('until')) : $first;
        $currentMonth = CarbonImmutable::now()->startOfMonth();

        if ($last->lt($first)) {
            $this->error('--until doit être postérieur ou égal à --month.');

            return self::FAILURE;
        }

        // Un mois n'est acquis qu'une fois travaillé : ni le mois en cours, ni un mois futur.
        if ($last->gte($currentMonth)) {
            $this->error('Seuls les mois écoulés peuvent être crédités (dernier possible : '.$currentMonth->subMonth()->format('m/Y').').');

            return self::FAILURE;
        }

        if ($first->diffInMonths($last) >= 24) {
            $this->error('La période ne peut pas dépasser 24 mois.');

            return self::FAILURE;
        }

        for ($month = $first; $month->lte($last); $month = $month->addMonth()) {
            $created = $balances->accrueMonth($month);
            $this->info("{$created} acquisition(s) créditée(s) pour ".$month->format('m/Y').'.');
        }

        return self::SUCCESS;
    }
}

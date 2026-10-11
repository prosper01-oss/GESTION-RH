<?php

namespace App\Console\Commands;

use App\Services\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('omit:accrue-leave {--month= : Mois à créditer (AAAA-MM), par défaut le mois précédent}')]
#[Description('Crédite les droits à congés acquis pour un mois écoulé (sans effet si déjà fait)')]
class AccrueLeave extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LeaveBalanceService $balances): int
    {
        $option = $this->option('month');

        if ($option !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $option)) {
            $this->error('Le mois doit être au format AAAA-MM.');

            return self::FAILURE;
        }

        $month = $option ? CarbonImmutable::createFromFormat('!Y-m', $option) : CarbonImmutable::now()->subMonthNoOverflow();

        $created = $balances->accrueMonth($month);
        $this->info("{$created} acquisition(s) créditée(s) pour ".$month->format('m/Y').'.');

        return self::SUCCESS;
    }
}

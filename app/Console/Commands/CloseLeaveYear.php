<?php

namespace App\Console\Commands;

use App\Services\LeaveBalanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('omit:close-leave-year {year? : Année à clôturer, par défaut l\'année précédente}')]
#[Description("Reporte ou périme les soldes restants d'une année et crédite les droits annuels de l'année suivante")]
class CloseLeaveYear extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LeaveBalanceService $balances): int
    {
        $year = (int) ($this->argument('year') ?? now()->year - 1);

        if ($year < 2000 || $year > now()->year) {
            $this->error("L'année doit être comprise entre 2000 et l'année en cours.");

            return self::FAILURE;
        }

        $result = $balances->closeYear($year);
        $this->info("Clôture {$year} : {$result['carried_over']} report(s), {$result['expired']} péremption(s), {$result['annual_credits']} crédit(s) annuel(s) pour ".($year + 1).'.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Notifications\LeaveBalanceReminder;
use App\Services\LeaveBalanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('omit:remind-leave-balance {--year= : Année de référence, par défaut l\'année en cours}')]
#[Description('Envoie à chaque agent actif une notification récapitulant son solde de congés disponible')]
class RemindLeaveBalance extends Command
{
    public function handle(LeaveBalanceService $balances): int
    {
        $year = (int) ($this->option('year') ?? now()->year);
        $leaveTypes = LeaveType::query()->active()->where('has_balance', true)->orderBy('name')->get();
        $sent = 0;

        Employee::query()
            ->where('status', EmployeeStatus::Actif)
            ->with('user')
            ->get()
            ->each(function (Employee $employee) use ($balances, $leaveTypes, $year, &$sent): void {
                if ($employee->user === null) {
                    return;
                }

                $summary = $leaveTypes->map(fn (LeaveType $type): array => [
                    'name' => $type->name,
                    'available' => $balances->summary($employee->leaveBalances()->where([
                        'leave_type_id' => $type->id,
                        'period_year' => $year,
                    ])->with('entries')->first())['available'],
                ])->filter(fn (array $item): bool => $item['available'] > 0)->values()->all();

                if ($summary === []) {
                    return;
                }

                $employee->user->notify(new LeaveBalanceReminder($summary, $year));
                $sent++;
            });

        $this->info("{$sent} rappel(s) de solde envoyé(s) pour l'année {$year}.");

        return self::SUCCESS;
    }
}

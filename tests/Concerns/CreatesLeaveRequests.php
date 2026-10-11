<?php

namespace Tests\Concerns;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;

/**
 * Données communes aux tests des demandes de congé.
 */
trait CreatesLeaveRequests
{
    protected function annualLeave(array $attributes = []): LeaveType
    {
        return LeaveType::factory()->withPolicy(['counting_mode' => 'calendar'])->create($attributes + ['name' => 'Congé annuel']);
    }

    /**
     * Agent disposant d'un solde de départ pour l'année en cours.
     */
    protected function agentWithBalance(LeaveType $leaveType, float $days = 10): User
    {
        $agent = User::factory()->forEmployee()->create();

        if ($days > 0) {
            app(LeaveBalanceService::class)->adjust($agent->employee, $leaveType, now()->year, $days, 'Solde de départ', $agent);
        }

        return $agent;
    }

    protected function available(Employee $employee, LeaveType $leaveType, ?int $year = null): float
    {
        return app(LeaveBalanceService::class)->summary(
            LeaveBalance::where(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'period_year' => $year ?? now()->year])->first()
        )['available'];
    }

    /**
     * Crée un brouillon par l'API et renvoie son identifiant.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function draft(User $agent, LeaveType $leaveType, array $overrides = []): int
    {
        return $this->actingAs($agent)->postJson('/api/v1/leave-requests', $overrides + [
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'reason' => 'Vacances',
        ])->assertCreated()->json('data.id');
    }

    protected function rh(): User
    {
        return $this->userWithRole(RoleName::RessourcesHumaines);
    }

    protected function medecinChef(): User
    {
        return $this->userWithRole(RoleName::MedecinChef);
    }
}

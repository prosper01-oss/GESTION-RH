<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeaveBalanceAdjustmentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rh_records_a_justified_correction_and_returns_201(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();
        $employee = Employee::factory()->create();
        $rh = $this->userWithRole(RoleName::RessourcesHumaines);

        $response = $this->actingAs($rh)->postJson("/api/v1/leave-balances/{$employee->id}/adjustments", [
            'leave_type_id' => $leaveType->id, 'period_year' => 2026, 'days' => -2.5, 'reason' => 'Erreur de saisie du mois de mars',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.days', -2.5)
            ->assertJsonPath('data.author', $rh->name);
        $this->assertDatabaseHas('leave_balance_entries', ['entry_type' => 'correction', 'created_by' => $rh->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave_balance.adjusted', 'entity_id' => $employee->id]);
    }

    public function test_correction_without_reason_or_with_quarter_days_returns_422(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->postJson("/api/v1/leave-balances/{$employee->id}/adjustments", ['leave_type_id' => $leaveType->id, 'period_year' => 2026, 'days' => 1.25])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason', 'days' => 'Le nombre de jours doit être un multiple de 0,5.']);
    }

    public function test_type_without_balance_returns_422(): void
    {
        $leaveType = LeaveType::factory()->withoutBalance()->withPolicy()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->postJson("/api/v1/leave-balances/{$employee->id}/adjustments", ['leave_type_id' => $leaveType->id, 'period_year' => 2026, 'days' => 1, 'reason' => 'Ajustement test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['leave_type_id' => "Ce type de congé n'est pas tenu en solde."]);
    }

    public function test_medecin_chef_gets_403(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::MedecinChef))
            ->postJson("/api/v1/leave-balances/{$employee->id}/adjustments", ['leave_type_id' => $leaveType->id, 'period_year' => 2026, 'days' => 1, 'reason' => 'Ajustement test'])
            ->assertForbidden();
        $this->assertDatabaseCount('leave_balance_entries', 0);
    }
}

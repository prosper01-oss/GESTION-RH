<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeaveBalanceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_agent_sees_own_balances_with_history(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create(['name' => 'Congé annuel']);
        LeaveType::factory()->withoutBalance()->withPolicy()->create();
        $agent = User::factory()->forEmployee()->create();
        app(LeaveBalanceService::class)->adjust($agent->employee, $leaveType, 2026, 12.5, 'Reprise des soldes papier', $this->userWithRole(RoleName::RessourcesHumaines));

        $response = $this->actingAs($agent)->getJson('/api/v1/leave-balances/me?year=2026');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.leave_type.name', 'Congé annuel')
            ->assertJsonPath('data.0.summary.available', 12.5)
            ->assertJsonPath('data.0.entries.0.entry_type', 'correction')
            ->assertJsonPath('data.0.entries.0.reason', 'Reprise des soldes papier');
    }

    public function test_type_without_movements_shows_a_zero_balance(): void
    {
        LeaveType::factory()->withPolicy()->create();
        $agent = User::factory()->forEmployee()->create();

        $this->actingAs($agent)->getJson('/api/v1/leave-balances/me')
            ->assertOk()
            ->assertJsonPath('data.0.summary.available', 0)
            ->assertJsonCount(0, 'data.0.entries');
    }

    public function test_account_without_employee_record_returns_404(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Administrateur))->getJson('/api/v1/leave-balances/me')->assertNotFound();
    }

    public function test_agent_gets_403_on_another_agent_balances(): void
    {
        $agent = User::factory()->forEmployee()->create();
        $other = Employee::factory()->create();

        $this->actingAs($agent)->getJson("/api/v1/leave-balances/{$other->id}")->assertForbidden();
    }

    public function test_medecin_chef_reads_an_agent_balances(): void
    {
        LeaveType::factory()->withPolicy()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::MedecinChef))->getJson("/api/v1/leave-balances/{$employee->id}")->assertOk();
    }
}

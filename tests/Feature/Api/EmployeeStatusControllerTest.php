<?php

namespace Tests\Feature\Api;

use App\Enums\EmployeeStatus;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EmployeeStatusControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deactivating_an_employee_also_deactivates_the_linked_account(): void
    {
        $agent = User::factory()->forEmployee()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->patchJson("/api/v1/employees/{$agent->employee_id}/status", ['status' => 'inactif']);

        $response->assertOk()->assertJsonPath('data.status', 'inactif');
        $this->assertSame(EmployeeStatus::Inactif, $agent->employee->fresh()->status);
        $this->assertFalse($agent->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee.status_changed', 'entity_id' => $agent->employee_id]);
    }

    public function test_reactivating_an_employee_reactivates_the_linked_account(): void
    {
        $employee = Employee::factory()->inactive()->create();
        $agent = User::factory()->forEmployee($employee)->inactive()->create();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->patchJson("/api/v1/employees/{$employee->id}/status", ['status' => 'actif'])
            ->assertOk();

        $this->assertTrue($agent->fresh()->is_active);
    }

    public function test_unknown_status_returns_422(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->patchJson("/api/v1/employees/{$employee->id}/status", ['status' => 'supprime'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_agent_gets_403_when_changing_own_status(): void
    {
        $agent = User::factory()->forEmployee()->create();

        $this->actingAs($agent)
            ->patchJson("/api/v1/employees/{$agent->employee_id}/status", ['status' => 'inactif'])
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_any_authenticated_user_lists_departments_with_headcount(): void
    {
        $department = Department::factory()->create();
        Employee::factory()->count(3)->for($department)->create();

        $this->actingAs(User::factory()->forEmployee()->create())
            ->getJson('/api/v1/departments')
            ->assertOk()
            ->assertJsonFragment(['id' => $department->id, 'employees_count' => 3]);
    }

    public function test_rh_creates_a_department_with_a_manager(): void
    {
        $manager = Employee::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->postJson('/api/v1/departments', ['code' => 'MED', 'name' => 'Médecine du travail', 'manager_employee_id' => $manager->id]);

        $response->assertCreated()->assertJsonPath('data.manager.id', $manager->id);
        $this->assertDatabaseHas('departments', ['code' => 'MED', 'manager_employee_id' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'department.created']);
    }

    public function test_agent_gets_403_when_creating_a_department(): void
    {
        $this->actingAs(User::factory()->forEmployee()->create())
            ->postJson('/api/v1/departments', ['code' => 'MED', 'name' => 'Médecine du travail'])
            ->assertForbidden();
    }

    public function test_duplicate_code_returns_422_but_own_code_is_accepted_on_update(): void
    {
        $existing = Department::factory()->create(['code' => 'MED']);
        $rh = $this->userWithRole(RoleName::RessourcesHumaines);

        $this->actingAs($rh)->postJson('/api/v1/departments', ['code' => 'MED', 'name' => 'Autre'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->actingAs($rh)->putJson("/api/v1/departments/{$existing->id}", ['code' => 'MED', 'name' => 'Nouveau nom'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nouveau nom');
    }
}

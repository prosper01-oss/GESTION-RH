<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_returns_401_without_a_session(): void
    {
        $this->getJson('/api/v1/employees')->assertUnauthorized();
    }

    /**
     * @return array<string, array{RoleName}>
     */
    public static function rolesWithoutDirectoryAccess(): array
    {
        return [
            'personnel' => [RoleName::Personnel],
            'administrateur' => [RoleName::Administrateur],
        ];
    }

    #[DataProvider('rolesWithoutDirectoryAccess')]
    public function test_index_returns_403_for_roles_without_directory_access(RoleName $role): void
    {
        $this->actingAs($this->userWithRole($role))->getJson('/api/v1/employees')->assertForbidden();
    }

    /**
     * @return array<string, array{RoleName}>
     */
    public static function rolesWithDirectoryAccess(): array
    {
        return [
            'rh' => [RoleName::RessourcesHumaines],
            'medecin-chef' => [RoleName::MedecinChef],
        ];
    }

    #[DataProvider('rolesWithDirectoryAccess')]
    public function test_index_lists_employees_for_roles_with_directory_access(RoleName $role): void
    {
        Employee::factory()->count(2)->create();

        $this->actingAs($this->userWithRole($role))->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_name_department_and_status(): void
    {
        $department = Department::factory()->create();
        $match = Employee::factory()->for($department)->create(['nom' => 'Rakoto']);
        Employee::factory()->for($department)->create(['nom' => 'Rabe']);
        Employee::factory()->create(['nom' => 'Rakotobe']);
        Employee::factory()->for($department)->inactive()->create(['nom' => 'Rakotoarisoa']);

        $response = $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->getJson("/api/v1/employees?search=Rakoto&department_id={$department->id}&status=actif");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_agent_can_view_own_record(): void
    {
        $user = User::factory()->forEmployee()->create();

        $this->actingAs($user)->getJson("/api/v1/employees/{$user->employee_id}")
            ->assertOk()
            ->assertJsonPath('data.matricule', $user->employee->matricule);
    }

    public function test_agent_gets_403_on_another_employee_record(): void
    {
        $user = User::factory()->forEmployee()->create();
        $other = Employee::factory()->create();

        $this->actingAs($user)->getJson("/api/v1/employees/{$other->id}")->assertForbidden();
    }

    public function test_rh_creates_an_employee_and_returns_201(): void
    {
        $rh = $this->userWithRole(RoleName::RessourcesHumaines);
        $department = Department::factory()->create();

        $response = $this->actingAs($rh)->postJson('/api/v1/employees', $this->validPayload($department));

        $response->assertCreated()
            ->assertJsonPath('data.matricule', 'OMIT-00125')
            ->assertJsonPath('data.status', 'actif')
            ->assertJsonPath('data.department.id', $department->id);
        $employee = Employee::where('matricule', 'OMIT-00125')->sole();
        $this->assertSame('Rasoanaivo', $employee->nom);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee.created', 'entity_id' => $employee->id, 'user_id' => $rh->id]);
    }

    /**
     * @return array<string, array{string, int, float}>
     */
    public static function hireDates(): array
    {
        return [
            'embauché avant l\'année : janvier à septembre' => ['2020-03-01', 9, 22.5],
            'embauché le 15 juin : juillet à septembre' => ['2026-06-15', 3, 7.5],
            'embauché le 1er juin : juin compris' => ['2026-06-01', 4, 10.0],
            'embauché ce mois-ci : rien encore' => ['2026-10-05', 0, 0.0],
        ];
    }

    #[DataProvider('hireDates')]
    public function test_creating_an_employee_credits_the_leave_already_earned_this_year(string $hireDate, int $months, float $available): void
    {
        $this->travelTo(now()->setDate(2026, 10, 18));
        $annualLeave = LeaveType::factory()->withPolicy(['annual_entitlement' => 30])->create();
        $department = Department::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->postJson('/api/v1/employees', ['hire_date' => $hireDate] + $this->validPayload($department));

        $response->assertCreated()->assertJsonPath('leave_months_credited', $months);
        $employee = Employee::where('matricule', 'OMIT-00125')->sole();
        $this->assertSame($available, app(LeaveBalanceService::class)->totalAvailableAllYears($employee, $annualLeave));
    }

    public function test_medecin_chef_gets_403_when_creating_an_employee(): void
    {
        $department = Department::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::MedecinChef))
            ->postJson('/api/v1/employees', $this->validPayload($department))
            ->assertForbidden();
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_store_with_empty_payload_returns_422_for_required_fields(): void
    {
        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->postJson('/api/v1/employees', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['matricule', 'nom', 'prenom', 'department_id', 'position', 'hire_date', 'contract_type']);
    }

    public function test_store_with_duplicate_matricule_returns_422(): void
    {
        $existing = Employee::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->postJson('/api/v1/employees', ['matricule' => $existing->matricule] + $this->validPayload($existing->department))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('matricule');
    }

    public function test_update_keeps_own_matricule_and_logs_old_and_new_values(): void
    {
        $employee = Employee::factory()->create(['position' => 'Infirmier']);

        $response = $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->putJson("/api/v1/employees/{$employee->id}", [
                'matricule' => $employee->matricule,
                'position' => 'Infirmier major',
            ] + $this->validPayload($employee->department));

        $response->assertOk()->assertJsonPath('data.position', 'Infirmier major');
        $log = AuditLog::where('action', 'employee.updated')->sole();
        $this->assertSame('Infirmier', $log->old_values['position']);
        $this->assertSame('Infirmier major', $log->new_values['position']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Department $department): array
    {
        return [
            'matricule' => 'OMIT-00125',
            'nom' => 'Rasoanaivo',
            'prenom' => 'Hery',
            'department_id' => $department->id,
            'position' => 'Infirmier',
            'hire_date' => '2020-03-01',
            'contract_type' => 'cdi',
            'phone_pro' => '+261 34 00 000 00',
            'email_pro' => 'hery.rasoanaivo@omit.mg',
        ];
    }
}

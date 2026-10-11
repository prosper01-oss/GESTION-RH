<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_sees_changed_fields_but_not_values_of_employee_records(): void
    {
        $employee = Employee::factory()->create();
        AuditLog::record('employee.updated', $employee, ['position' => 'Infirmier'], ['position' => 'Infirmier major']);

        $response = $this->actingAs($this->userWithRole(RoleName::Administrateur))
            ->getJson('/api/v1/admin/audit-logs?action=employee.updated');

        $response->assertOk()
            ->assertJsonPath('data.0.changed_fields', ['position'])
            ->assertJsonMissingPath('data.0.new_values')
            ->assertJsonMissingPath('data.0.old_values');
    }

    public function test_admin_sees_values_of_account_changes(): void
    {
        $user = User::factory()->create();
        AuditLog::record('user.roles_updated', $user, ['roles' => ['personnel']], ['roles' => ['rh']]);

        $this->actingAs($this->userWithRole(RoleName::Administrateur))
            ->getJson('/api/v1/admin/audit-logs?action=user.roles_updated')
            ->assertOk()
            ->assertJsonPath('data.0.new_values', ['roles' => ['rh']]);
    }

    public function test_rh_gets_403_on_the_audit_log(): void
    {
        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->getJson('/api/v1/admin/audit-logs')
            ->assertForbidden();
    }
}

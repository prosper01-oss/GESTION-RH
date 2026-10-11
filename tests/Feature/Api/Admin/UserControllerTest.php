<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_creates_an_account_linked_by_matricule_with_a_temporary_password(): void
    {
        $employee = Employee::factory()->create(['matricule' => 'OMIT-00042']);

        $response = $this->actingAs($this->userWithRole(RoleName::Administrateur))->postJson('/api/v1/admin/users', [
            'name' => 'Hery Rasoanaivo',
            'email' => 'hery@omit.mg',
            'matricule' => 'OMIT-00042',
            'roles' => ['personnel', 'rh'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonPath('data.roles', ['personnel', 'rh']);
        $user = User::where('email', 'hery@omit.mg')->sole();
        $this->assertTrue(Hash::check($response->json('temporary_password'), $user->password));
    }

    public function test_rh_gets_403_when_creating_an_account(): void
    {
        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->postJson('/api/v1/admin/users', ['name' => 'X', 'email' => 'x@omit.mg', 'roles' => ['personnel']])
            ->assertForbidden();
    }

    public function test_matricule_already_linked_to_an_account_returns_422(): void
    {
        $agent = User::factory()->forEmployee()->create();

        $this->actingAs($this->userWithRole(RoleName::Administrateur))->postJson('/api/v1/admin/users', [
            'name' => 'Doublon',
            'email' => 'doublon@omit.mg',
            'matricule' => $agent->employee->matricule,
            'roles' => ['personnel'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['matricule' => 'Cet employé a déjà un compte.']);
    }

    public function test_unknown_role_returns_422(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Administrateur))->postJson('/api/v1/admin/users', [
            'name' => 'X',
            'email' => 'x@omit.mg',
            'roles' => ['super_admin'],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles.0');
    }

    public function test_admin_replaces_the_roles_of_an_account(): void
    {
        $agent = User::factory()->forEmployee()->create();

        $this->actingAs($this->userWithRole(RoleName::Administrateur))
            ->patchJson("/api/v1/admin/users/{$agent->id}/roles", ['roles' => ['personnel', 'medecin_chef']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['personnel', 'medecin_chef']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.roles_updated', 'entity_id' => $agent->id]);
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = $this->userWithRole(RoleName::Administrateur);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$admin->id}/roles", ['roles' => ['personnel']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles');
        $this->assertTrue($admin->fresh()->hasRole(RoleName::Administrateur));
    }

    public function test_password_reset_forces_a_change_at_next_login(): void
    {
        $agent = User::factory()->forEmployee()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::Administrateur))
            ->postJson("/api/v1/admin/users/{$agent->id}/reset-password");

        $response->assertOk();
        $agent->refresh();
        $this->assertTrue($agent->must_change_password);
        $this->assertTrue(Hash::check($response->json('temporary_password'), $agent->password));
    }
}

<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserModuleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_grants_and_revokes_pharmacy_modules_on_a_single_account(): void
    {
        $admin = $this->userWithRole(RoleName::Administrateur);
        $caissier = $this->userWithRole(RoleName::Caissier);

        $this->actingAs($admin)->putJson("/api/v1/admin/users/{$caissier->id}/modules", ['modules' => ['pharma.articles', 'pharma.cmm']])
            ->assertOk()
            ->assertJsonPath('data.modules', ['pharma.articles', 'pharma.cmm']);

        $this->actingAs($caissier->fresh())->getJson('/api/v1/pharma/articles')->assertOk();
        $this->actingAs($caissier->fresh())->getJson('/api/v1/me')->assertJsonPath('data.permissions', fn ($permissions) => in_array('pharma.cmm', $permissions, true) && in_array('pharma.caisse', $permissions, true));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.modules_updated', 'entity_id' => $caissier->id]);

        $this->actingAs($admin)->putJson("/api/v1/admin/users/{$caissier->id}/modules", ['modules' => []])->assertOk();
        $this->actingAs($caissier->fresh())->getJson('/api/v1/pharma/articles')->assertForbidden();
    }

    public function test_only_pharmacy_modules_can_be_granted_individually(): void
    {
        $admin = $this->userWithRole(RoleName::Administrateur);
        $agent = $this->userWithRole(RoleName::Personnel);

        $this->actingAs($admin)->putJson("/api/v1/admin/users/{$agent->id}/modules", ['modules' => ['users.manage']])
            ->assertUnprocessable()->assertJsonValidationErrors('modules.0');
    }

    public function test_non_admins_cannot_change_modules(): void
    {
        $rh = $this->userWithRole(RoleName::RessourcesHumaines);
        $agent = $this->userWithRole(RoleName::Personnel);

        $this->actingAs($rh)->putJson("/api/v1/admin/users/{$agent->id}/modules", ['modules' => ['pharma.caisse']])->assertForbidden();
    }

    public function test_pharmacy_roles_can_be_assigned_when_creating_an_account(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Administrateur))->postJson('/api/v1/admin/users', [
            'name' => 'Naly', 'email' => 'naly@omit.mg', 'roles' => ['pharmacien'],
        ])->assertCreated()->assertJsonPath('data.roles', ['pharmacien']);
    }
}

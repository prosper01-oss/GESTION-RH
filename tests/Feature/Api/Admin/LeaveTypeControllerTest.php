<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleName;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeaveTypeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{RoleName}>
     */
    public static function rolesThatManageSettings(): array
    {
        return ['rh' => [RoleName::RessourcesHumaines], 'administrateur' => [RoleName::Administrateur]];
    }

    #[DataProvider('rolesThatManageSettings')]
    public function test_creates_a_leave_type_with_its_rules_and_returns_201(RoleName $role): void
    {
        $response = $this->actingAs($this->userWithRole($role))->postJson('/api/v1/admin/leave-types', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.code', 'CA')
            ->assertJsonPath('data.policy.annual_entitlement', 30)
            ->assertJsonPath('data.policy.monthly_entitlement', 2.5)
            ->assertJsonPath('data.policy.effective_from', now()->startOfYear()->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave_type.created']);
    }

    /**
     * @return array<string, array{RoleName}>
     */
    public static function rolesWithoutSettingsAccess(): array
    {
        return ['personnel' => [RoleName::Personnel], 'medecin-chef' => [RoleName::MedecinChef]];
    }

    #[DataProvider('rolesWithoutSettingsAccess')]
    public function test_returns_403_for_roles_without_settings_access(RoleName $role): void
    {
        $this->actingAs($this->userWithRole($role))->postJson('/api/v1/admin/leave-types', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('leave_types', 0);
    }

    public function test_changing_the_rules_creates_a_new_dated_version_and_keeps_history(): void
    {
        $leaveType = LeaveType::factory()->withPolicy(['annual_entitlement' => 30])->create(['code' => 'CA', 'name' => 'Congé annuel']);

        $response = $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->putJson("/api/v1/admin/leave-types/{$leaveType->id}", $this->payload(['policy' => [
                'accrual_rule' => 'monthly', 'annual_entitlement' => 24, 'counting_mode' => 'working', 'effective_from' => '2026-01-01',
            ]]));

        $response->assertOk()
            ->assertJsonPath('data.policy.annual_entitlement', 24)
            ->assertJsonPath('data.policy.counting_mode', 'working')
            ->assertJsonCount(2, 'data.policies');
        $this->assertSame(30.0, (float) $leaveType->policyAt(now()->setDate(2025, 6, 1))->annual_entitlement);
    }

    public function test_updating_without_rule_changes_does_not_create_a_version(): void
    {
        $leaveType = LeaveType::factory()->withPolicy(['annual_entitlement' => 30, 'counting_mode' => 'calendar'])->create(['code' => 'CA', 'name' => 'Congé annuel']);

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->putJson("/api/v1/admin/leave-types/{$leaveType->id}", $this->payload(['name' => 'Congé payé']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Congé payé');

        $this->assertSame(1, $leaveType->policies()->count());
    }

    public function test_changing_the_rules_twice_on_the_same_day_updates_that_day_version(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create(['code' => 'CA', 'name' => 'Congé annuel']);
        $rh = $this->userWithRole(RoleName::RessourcesHumaines);

        foreach ([24, 26] as $entitlement) {
            $this->actingAs($rh)->putJson("/api/v1/admin/leave-types/{$leaveType->id}", $this->payload(['policy' => [
                'accrual_rule' => 'monthly', 'annual_entitlement' => $entitlement, 'counting_mode' => 'calendar', 'effective_from' => '2026-10-01',
            ]]))->assertOk();
        }

        $this->assertSame(2, $leaveType->policies()->count());
        $this->assertSame(26.0, (float) $leaveType->policyAt(now()->setDate(2026, 10, 2))->annual_entitlement);
    }

    public function test_invalid_rules_return_422(): void
    {
        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->postJson('/api/v1/admin/leave-types', $this->payload(['policy' => ['accrual_rule' => 'weekly', 'annual_entitlement' => -1, 'counting_mode' => 'calendar']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['policy.accrual_rule', 'policy.annual_entitlement']);
    }

    public function test_public_list_contains_only_active_types(): void
    {
        LeaveType::factory()->withPolicy()->create(['name' => 'Actif']);
        LeaveType::factory()->inactive()->withPolicy()->create(['name' => 'Inactif']);

        $this->actingAs($this->userWithRole(RoleName::Personnel))->getJson('/api/v1/leave-types')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Actif');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'code' => 'CA',
            'name' => 'Congé annuel',
            'is_paid' => true,
            'has_balance' => true,
            'requires_attachment' => false,
            'is_medical' => false,
            'allows_half_day' => true,
            'is_active' => true,
            'policy' => ['accrual_rule' => 'monthly', 'annual_entitlement' => 30, 'counting_mode' => 'calendar', 'carry_over_max' => null],
        ], $overrides);
    }
}

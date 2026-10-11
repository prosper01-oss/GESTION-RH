<?php

namespace Tests\Feature\Api;

use App\Enums\RequestStatus;
use App\Enums\RoleName;
use App\Models\Delegation;
use App\Models\EmployeeRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

class DelegationControllerTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_delegate_decides_during_the_period_and_the_decision_names_the_replaced_person(): void
    {
        $medecinChef = $this->medecinChef();
        $delegate = User::factory()->forEmployee()->create();
        $request = EmployeeRequest::factory()->leave(LeaveType::factory()->withoutBalance()->withPolicy()->create())->status(RequestStatus::TransmiseMedecin)->create();

        $this->actingAs($medecinChef)->postJson('/api/v1/delegations', [
            'role' => 'medecin_chef', 'delegate_id' => $delegate->id, 'start_date' => now()->toDateString(), 'end_date' => now()->addDays(7)->toDateString(),
        ])->assertCreated();

        $this->actingAs($delegate)->getJson('/api/v1/me')->assertJsonFragment(['requests.decide']);
        $this->actingAs($delegate)->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'approuver'])
            ->assertOk()
            ->assertJsonPath('data.history.0.actor', $delegate->name)
            ->assertJsonPath('data.history.0.on_behalf_of', $medecinChef->name);
    }

    public function test_delegate_has_no_rights_outside_the_period_or_after_revocation(): void
    {
        $medecinChef = $this->medecinChef();
        $delegate = User::factory()->forEmployee()->create();
        $request = EmployeeRequest::factory()->leave(LeaveType::factory()->withPolicy()->create())->status(RequestStatus::TransmiseMedecin)->create();
        $delegationId = $this->actingAs($medecinChef)->postJson('/api/v1/delegations', [
            'role' => 'medecin_chef', 'delegate_id' => $delegate->id, 'start_date' => now()->addDays(2)->toDateString(), 'end_date' => now()->addDays(7)->toDateString(),
        ])->json('data.id');

        $this->actingAs($delegate)->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'approuver'])->assertForbidden();

        $this->travel(3)->days();
        $this->actingAs($medecinChef)->deleteJson("/api/v1/delegations/{$delegationId}")->assertOk();
        $this->actingAs(User::find($delegate->id))->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'approuver'])->assertForbidden();
    }

    public function test_delegate_cannot_decide_on_a_request_of_the_replaced_person(): void
    {
        $leaveType = $this->annualLeave();
        $medecinChef = $this->agentWithBalance($leaveType);
        $medecinChef->roles()->attach(Role::where('name', 'medecin_chef')->first());
        $delegate = User::factory()->forEmployee()->create();
        Delegation::create([
            'delegator_id' => $medecinChef->id, 'delegate_id' => $delegate->id, 'role' => RoleName::MedecinChef,
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(7)->toDateString(),
        ]);
        $request = EmployeeRequest::factory()->for($medecinChef->employee)->leave($leaveType)->status(RequestStatus::TransmiseMedecin)->create();

        $this->actingAs($delegate)->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'approuver'])
            ->assertForbidden()
            ->assertJsonPath('message', 'En tant que suppléant, vous ne pouvez pas traiter les demandes de la personne que vous remplacez.');
    }

    public function test_rh_can_delegate_the_control_step(): void
    {
        $rh = $this->rh();
        $delegate = User::factory()->forEmployee()->create();
        $request = EmployeeRequest::factory()->leave(LeaveType::factory()->withPolicy()->create(['has_balance' => false]))->status(RequestStatus::Soumise)->create();
        Delegation::create([
            'delegator_id' => $rh->id, 'delegate_id' => $delegate->id, 'role' => RoleName::RessourcesHumaines,
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        ]);

        $this->actingAs($delegate)->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'transmettre'])->assertOk();
        $this->actingAs($delegate)->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'approuver'])->assertForbidden();
    }

    public function test_agent_without_a_delegable_role_gets_403(): void
    {
        $agent = User::factory()->forEmployee()->create();

        $this->actingAs($agent)->postJson('/api/v1/delegations', [
            'role' => 'medecin_chef', 'delegate_id' => User::factory()->create()->id, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_self_delegation_and_overlapping_periods_return_422(): void
    {
        $medecinChef = $this->medecinChef();
        $delegate = User::factory()->create();
        $period = ['start_date' => now()->toDateString(), 'end_date' => now()->addDays(5)->toDateString()];

        $this->actingAs($medecinChef)->postJson('/api/v1/delegations', ['role' => 'medecin_chef', 'delegate_id' => $medecinChef->id] + $period)
            ->assertUnprocessable()->assertJsonValidationErrors(['delegate_id' => 'Vous ne pouvez pas être votre propre suppléant.']);
        $this->actingAs($medecinChef)->postJson('/api/v1/delegations', ['role' => 'medecin_chef', 'delegate_id' => $delegate->id] + $period)->assertCreated();
        $this->actingAs($medecinChef)->postJson('/api/v1/delegations', ['role' => 'medecin_chef', 'delegate_id' => $delegate->id] + $period)
            ->assertUnprocessable()->assertJsonValidationErrors(['start_date' => 'Une suppléance existe déjà sur cette période.']);
    }
}

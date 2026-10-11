<?php

namespace Tests\Feature\Api;

use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

class RequestTransitionControllerTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_full_circuit_reserves_then_consumes_the_balance(): void
    {
        $this->freezeTime();
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType, 10);
        $rh = $this->rh();
        $medecinChef = $this->medecinChef();
        $id = $this->draft($agent, $leaveType);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'soumise')
            ->assertJsonPath('data.reference', sprintf('OMIT-CG-%d-00001', now()->year));
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'transmettre'])
            ->assertOk()->assertJsonPath('data.status', 'transmise_medecin');
        $this->actingAs($medecinChef)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver', 'comment' => 'Accordé'])
            ->assertOk()->assertJsonPath('data.status', 'approuvee');
        $this->assertSame(5.0, $this->available($agent->employee, $leaveType));

        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/finalize")->assertOk()->assertJsonPath('data.status', 'finalisee');
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/complete")->assertOk()->assertJsonPath('data.status', 'terminee');

        $summary = app(LeaveBalanceService::class)->summary($agent->employee->leaveBalances()->first());
        $this->assertSame(5.0, $summary['consumed']);
        $this->assertSame(0.0, $summary['reserved']);
        $this->assertSame(5.0, $summary['available']);
        $this->assertSame(
            ['brouillon', 'soumise', 'transmise_medecin', 'approuvee', 'finalisee', 'terminee'],
            EmployeeRequest::find($id)->history()->orderBy('id')->pluck('to_status')->map->value->all(),
        );
    }

    public function test_medecin_chef_cannot_skip_the_rh_step(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $id = $this->draft($agent, $leaveType);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();

        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])
            ->assertConflict();
        $this->assertSame(RequestStatus::Soumise, EmployeeRequest::find($id)->status);
    }

    public function test_rh_cannot_take_the_final_decision(): void
    {
        $leaveType = $this->annualLeave();
        $request = EmployeeRequest::factory()->leave($leaveType)->status(RequestStatus::TransmiseMedecin)->create();

        $this->actingAs($this->rh())->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'approuver'])
            ->assertForbidden();
    }

    public function test_refusal_requires_a_reason_and_records_author_date_and_reason(): void
    {
        $this->freezeSecond();
        $leaveType = $this->annualLeave();
        $request = EmployeeRequest::factory()->leave($leaveType)->status(RequestStatus::TransmiseMedecin)->create();
        $medecinChef = $this->medecinChef();

        $this->actingAs($medecinChef)->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'refuser'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comment' => 'Un commentaire est obligatoire pour cette décision.']);

        $this->actingAs($medecinChef)->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'refuser', 'comment' => 'Service en sous-effectif'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refusee')
            ->assertJsonPath('data.history.0.actor', $medecinChef->name)
            ->assertJsonPath('data.history.0.comment', 'Service en sous-effectif');
        $this->assertEquals(now(), $request->history()->sole()->created_at);
    }

    public function test_rh_cannot_process_own_request_and_does_not_see_it_in_the_queue(): void
    {
        $leaveType = $this->annualLeave();
        $rhAgent = $this->agentWithBalance($leaveType);
        $rhAgent->roles()->attach(Role::where('name', 'rh')->first());
        $id = $this->draft($rhAgent, $leaveType);
        $this->actingAs($rhAgent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();

        $this->actingAs($rhAgent->fresh())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'transmettre'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Vous ne pouvez pas valider votre propre demande.');
        $this->actingAs($rhAgent->fresh())->getJson('/api/v1/approvals/pending?queue=control')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->rh())->getJson('/api/v1/approvals/pending?queue=control')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_insufficient_balance_at_submission_returns_409(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType, 3);
        $id = $this->draft($agent, $leaveType);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")
            ->assertConflict()
            ->assertJsonPath('message', 'Solde insuffisant : 5 jour(s) demandé(s), 3 jour(s) disponible(s) en tenant compte des demandes en cours.');
    }

    public function test_two_pending_requests_cannot_exceed_the_balance_together(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType, 8);
        $first = $this->draft($agent, $leaveType);
        $second = $this->draft($agent, $leaveType, ['start_date' => now()->addDays(30)->toDateString(), 'end_date' => now()->addDays(34)->toDateString()]);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$first}/submit")->assertOk();
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$second}/submit")->assertConflict();
    }

    public function test_approval_is_refused_when_the_balance_became_insufficient(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType, 5);
        $id = $this->draft($agent, $leaveType);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();
        $this->actingAs($this->rh())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'transmettre'])->assertOk();
        app(LeaveBalanceService::class)->adjust($agent->employee, $leaveType, now()->year, -2, 'Erreur de saisie', $this->rh());

        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertConflict();

        $this->assertSame(RequestStatus::TransmiseMedecin, EmployeeRequest::find($id)->status);
        $this->assertSame(3.0, $this->available($agent->employee, $leaveType));
    }

    public function test_overlapping_period_returns_422_on_submission(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType, 20);
        $first = $this->draft($agent, $leaveType);
        $second = $this->draft($agent, $leaveType, ['start_date' => now()->addDays(12)->toDateString(), 'end_date' => now()->addDays(16)->toDateString()]);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$first}/submit")->assertOk();

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$second}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_cancelling_an_approved_leave_before_it_starts_releases_the_reservation(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType, 10);
        $id = $this->approvedRequest($agent, $leaveType);
        $this->assertSame(5.0, $this->available($agent->employee, $leaveType));

        $this->actingAs($this->rh())->postJson("/api/v1/leave-requests/{$id}/cancel", ['comment' => "Demande de l'agent"])
            ->assertOk()
            ->assertJsonPath('data.status', 'annulee');

        $this->assertSame(10.0, $this->available($agent->employee, $leaveType));
    }

    public function test_started_leave_cannot_be_cancelled_but_can_be_interrupted(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType, 10);
        $id = $this->approvedRequest($agent, $leaveType);
        $rh = $this->rh();
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/finalize")->assertOk();
        $this->travel(11)->days();

        $this->actingAs($rh)->postJson("/api/v1/leave-requests/{$id}/cancel")->assertConflict();

        $this->actingAs($rh)->postJson("/api/v1/leave-requests/{$id}/interrupt", ['actual_end_date' => now()->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.status', 'terminee')
            ->assertJsonPath('data.leave.consumed_days', 2);
        $this->assertSame(8.0, $this->available($agent->employee, $leaveType));
    }

    public function test_agent_withdraws_a_submitted_request(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $id = $this->draft($agent, $leaveType);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'annulee');
    }

    public function test_agent_cannot_cancel_an_approved_request_directly(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $id = $this->approvedRequest($agent, $leaveType);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/cancel")->assertForbidden();
    }

    public function test_medecin_chef_sends_back_to_rh_who_can_forward_again(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $rh = $this->rh();
        $id = $this->draft($agent, $leaveType);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit");
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'transmettre']);

        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'renvoyer_rh', 'comment' => 'Vérifier le remplacement'])
            ->assertOk()->assertJsonPath('data.status', 'renvoyee_rh');
        $this->actingAs($rh)->getJson('/api/v1/approvals/pending?queue=control')->assertJsonCount(1, 'data');
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'transmettre'])
            ->assertOk()->assertJsonPath('data.status', 'transmise_medecin');
    }

    public function test_request_sent_back_for_correction_can_be_edited_and_resubmitted(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $id = $this->draft($agent, $leaveType);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit");
        $this->actingAs($this->rh())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'a_corriger', 'comment' => 'Dates à préciser'])->assertOk();

        $this->actingAs($agent)->putJson("/api/v1/leave-requests/{$id}", [
            'leave_type_id' => $leaveType->id, 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString(),
        ])->assertOk()->assertJsonPath('data.leave.requested_days', 3);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.reference', sprintf('OMIT-CG-%d-00001', now()->year));
    }

    public function test_finished_finalized_leaves_are_closed_by_the_scheduled_command(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType, 10);
        $id = $this->approvedRequest($agent, $leaveType);
        $this->actingAs($this->rh())->postJson("/api/v1/requests/{$id}/finalize")->assertOk();
        $this->travel(20)->days();

        $this->artisan('omit:close-finished-requests')->expectsOutputToContain('1 demande(s) clôturée(s)')->assertSuccessful();

        $this->assertSame(RequestStatus::Terminee, EmployeeRequest::find($id)->status);
        $this->assertNull(EmployeeRequest::find($id)->history()->latest('id')->first()->actor_id);
        $this->assertSame(5.0, $this->available($agent->employee, $leaveType));
    }

    private function approvedRequest(User $agent, LeaveType $leaveType): int
    {
        $id = $this->draft($agent, $leaveType);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();
        $this->actingAs($this->rh())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'transmettre'])->assertOk();
        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertOk();

        return $id;
    }
}

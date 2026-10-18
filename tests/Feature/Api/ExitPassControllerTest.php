<?php

namespace Tests\Feature\Api;

use App\Enums\RequestStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\User;
use App\Notifications\RequestUpdate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

/**
 * Règle R7 : sortie de 2 h au plus, validée par le chef direct (à défaut le Médecin-Chef) ;
 * validée, elle est autorisée sans finalisation par les RH.
 */
class ExitPassControllerTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_exit_longer_than_two_hours_returns_422(): void
    {
        $this->actingAs(User::factory()->forEmployee()->create())->postJson('/api/v1/exit-passes', [
            'departure_at' => now()->addHour()->toDateTimeString(),
            'expected_return_at' => now()->addHours(3)->addMinute()->toDateTimeString(),
            'destination' => 'Banque',
            'reason' => 'Démarche bancaire',
        ])->assertUnprocessable()->assertJsonValidationErrors(['expected_return_at' => 'Une sortie dure au plus 2 h.']);
    }

    public function test_department_manager_approves_and_the_exit_is_authorized_without_finalization(): void
    {
        [$agent, $manager] = $this->agentWithManager();
        $id = $this->submittedExitPass($agent);

        $this->actingAs($manager)->getJson('/api/v1/approvals/pending?queue=exits')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($manager)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approuvee')
            ->assertJsonPath('data.can_record_exit', true);
    }

    public function test_medecin_chef_approves_when_the_department_has_no_manager(): void
    {
        $agent = User::factory()->forEmployee()->create();
        $id = $this->submittedExitPass($agent);
        $medecinChef = $this->medecinChef();

        $this->actingAs($medecinChef)->getJson('/api/v1/approvals/pending?queue=exits')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($medecinChef)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertOk();
    }

    public function test_the_manager_own_exit_goes_to_the_medecin_chef(): void
    {
        [, $manager] = $this->agentWithManager();
        $id = $this->submittedExitPass($manager);

        $this->actingAs($manager)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertForbidden();
        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertOk();
    }

    public function test_medecin_chef_cannot_approve_when_a_manager_exists(): void
    {
        [$agent] = $this->agentWithManager();
        $id = $this->submittedExitPass($agent);

        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertForbidden();
    }

    public function test_rh_and_another_agent_cannot_approve(): void
    {
        [$agent] = $this->agentWithManager();
        $id = $this->submittedExitPass($agent);

        $this->actingAs($this->rh())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertForbidden();
        $this->actingAs(User::factory()->forEmployee()->create())->getJson("/api/v1/requests/{$id}")->assertForbidden();
    }

    public function test_refusal_by_the_manager_requires_a_reason(): void
    {
        [$agent, $manager] = $this->agentWithManager();
        $id = $this->submittedExitPass($agent);

        $this->actingAs($manager)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'refuser'])->assertUnprocessable();
        $this->actingAs($manager)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'refuser', 'comment' => 'Service chargé'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refusee');
    }

    public function test_manager_records_departure_and_return_which_closes_the_exit(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        [$agent, $manager] = $this->agentWithManager();
        $id = $this->approvedExitPass($agent, $manager);

        $this->actingAs($manager)->postJson("/api/v1/exit-passes/{$id}/record-departure")->assertOk();
        $this->travel(90)->minutes();
        $this->actingAs($manager)->postJson("/api/v1/exit-passes/{$id}/record-return")
            ->assertOk()
            ->assertJsonPath('data.status', 'terminee');

        $this->assertSame(sprintf('OMIT-BS-%d-00001', now()->year), EmployeeRequest::find($id)->reference);
    }

    public function test_rh_follows_overdue_exits(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        [$agent, $manager] = $this->agentWithManager();
        $rh = $this->rh();
        $id = $this->approvedExitPass($agent, $manager);
        $this->actingAs($rh)->postJson("/api/v1/exit-passes/{$id}/record-departure")->assertOk();

        $this->actingAs($rh)->getJson('/api/v1/exit-passes?scope=all&state=en_retard')->assertOk()->assertJsonCount(0, 'data');
        $this->travel(4)->hours();

        $this->actingAs($rh)->getJson('/api/v1/exit-passes?scope=all&state=en_retard')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.exit_pass.is_overdue', true);
    }

    public function test_exit_cannot_be_closed_without_a_recorded_return(): void
    {
        [$agent, $manager] = $this->agentWithManager();
        $id = $this->approvedExitPass($agent, $manager);

        $this->actingAs($manager)->postJson("/api/v1/requests/{$id}/complete")->assertConflict();
        $this->actingAs($manager)->postJson("/api/v1/exit-passes/{$id}/record-return")->assertConflict();
    }

    public function test_only_rh_cancel_an_authorized_exit_and_only_before_departure(): void
    {
        [$agent, $manager] = $this->agentWithManager();
        $rh = $this->rh();
        $first = $this->approvedExitPass($agent, $manager);
        $second = $this->approvedExitPass($agent, $manager, 4);

        $this->actingAs($manager)->postJson("/api/v1/requests/{$first}/cancel")->assertForbidden();
        $this->actingAs($agent)->postJson("/api/v1/requests/{$first}/cancel")->assertForbidden();
        $this->actingAs($rh)->postJson("/api/v1/requests/{$first}/cancel")->assertOk()->assertJsonPath('data.status', 'annulee');

        $this->actingAs($rh)->postJson("/api/v1/exit-passes/{$second}/record-departure")->assertOk();
        $this->actingAs($rh)->postJson("/api/v1/requests/{$second}/cancel")->assertConflict();
        $this->assertSame(RequestStatus::Approuvee, EmployeeRequest::find($second)->status);
    }

    public function test_agent_cannot_record_own_departure(): void
    {
        [$agent, $manager] = $this->agentWithManager();
        $id = $this->approvedExitPass($agent, $manager);

        $this->actingAs($agent)->postJson("/api/v1/exit-passes/{$id}/record-departure")->assertForbidden();
    }

    public function test_submission_notifies_the_manager_and_approval_informs_rh(): void
    {
        [$agent, $manager] = $this->agentWithManager();
        $rh = $this->rh();
        Notification::fake();

        $id = $this->submittedExitPass($agent);
        Notification::assertSentTo($manager, RequestUpdate::class, fn (RequestUpdate $notification): bool => $notification->title === 'Sortie à valider');
        Notification::assertNotSentTo($rh, RequestUpdate::class);

        $this->actingAs($manager)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertOk();
        Notification::assertSentTo($agent, RequestUpdate::class, fn (RequestUpdate $notification): bool => $notification->title === 'Sortie autorisée');
        Notification::assertSentTo($rh, RequestUpdate::class, fn (RequestUpdate $notification): bool => str_starts_with($notification->message, 'Pour information'));
    }

    public function test_missing_destination_and_reason_return_422(): void
    {
        $this->actingAs(User::factory()->forEmployee()->create())->postJson('/api/v1/exit-passes', [
            'departure_at' => now()->addHour()->toDateTimeString(),
            'expected_return_at' => now()->addHours(2)->toDateTimeString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['destination', 'reason']);
    }

    /**
     * Agent d'un service dont le responsable a un compte actif.
     *
     * @return array{User, User}
     */
    private function agentWithManager(): array
    {
        $department = Department::factory()->create();
        $manager = User::factory()->forEmployee(Employee::factory()->for($department)->create())->create();
        $department->update(['manager_employee_id' => $manager->employee_id]);
        $agent = User::factory()->forEmployee(Employee::factory()->for($department)->create())->create();

        return [$agent, $manager];
    }

    private function submittedExitPass(User $agent, int $startsInHours = 1): int
    {
        $id = $this->actingAs($agent)->postJson('/api/v1/exit-passes', [
            'departure_at' => now()->addHours($startsInHours)->toDateTimeString(),
            'expected_return_at' => now()->addHours($startsInHours + 2)->toDateTimeString(),
            'destination' => 'Banque, centre-ville',
            'reason' => 'Démarche bancaire',
        ])->assertCreated()->json('data.id');

        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/submit")->assertOk();

        return $id;
    }

    private function approvedExitPass(User $agent, User $manager, int $startsInHours = 1): int
    {
        $id = $this->submittedExitPass($agent, $startsInHours);
        $this->actingAs($manager)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertOk();

        return $id;
    }
}

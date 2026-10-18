<?php

namespace Tests\Feature\Api;

use App\Models\EmployeeRequest;
use App\Models\LeaveType;
use App\Models\PermissionType;
use App\Models\User;
use App\Notifications\RequestUpdate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

/**
 * Règles de gestion de l'OMIT contrôlées à l'envoi d'une demande : R4, R6 et R8.
 */
class RequestSubmissionRulesTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_leave_submitted_less_than_fifteen_days_ahead_returns_422(): void
    {
        $leaveType = $this->annualLeave(['min_notice_days' => 15]);
        $agent = $this->agentWithBalance($leaveType);
        $id = $this->draft($agent, $leaveType);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date' => "Une demande de congé annuel doit être déposée au moins 15 jours à l'avance. En cas d'urgence, cochez « Urgence » et indiquez le motif."]);
    }

    public function test_leave_submitted_fifteen_days_ahead_is_accepted(): void
    {
        $leaveType = $this->annualLeave(['min_notice_days' => 15]);
        $agent = $this->agentWithBalance($leaveType);
        $id = $this->draft($agent, $leaveType, ['start_date' => now()->addDays(15)->toDateString(), 'end_date' => now()->addDays(16)->toDateString()]);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();
    }

    public function test_urgent_leave_is_accepted_with_a_reason_and_refused_without(): void
    {
        $leaveType = $this->annualLeave(['min_notice_days' => 15]);
        $agent = $this->agentWithBalance($leaveType);
        $withoutReason = $this->draft($agent, $leaveType, ['is_urgent' => true, 'reason' => '']);
        $withReason = $this->draft($agent, $leaveType, [
            'is_urgent' => true, 'reason' => 'Décès dans la famille',
            'start_date' => now()->addDays(20)->toDateString(), 'end_date' => now()->addDays(21)->toDateString(),
        ]);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$withoutReason}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason' => "Indiquez le motif de l'urgence."]);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$withReason}/submit")
            ->assertOk()
            ->assertJsonPath('data.leave.is_urgent', true);
    }

    public function test_resubmission_after_correction_keeps_the_first_submission_date(): void
    {
        $leaveType = $this->annualLeave(['min_notice_days' => 15]);
        $agent = $this->agentWithBalance($leaveType);
        $id = $this->draft($agent, $leaveType, ['start_date' => now()->addDays(16)->toDateString(), 'end_date' => now()->addDays(17)->toDateString()]);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();
        $this->actingAs($this->rh())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'a_corriger', 'comment' => 'Préciser le motif'])->assertOk();
        $this->travel(3)->days();

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();
    }

    public function test_leave_overlapping_a_permission_returns_422(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $day = now()->addDays(12)->toDateString();
        $permission = $this->actingAs($agent)->postJson('/api/v1/permission-requests', [
            'permission_type_id' => PermissionType::factory()->create()->id, 'start_at' => "{$day} 08:00", 'end_at' => "{$day} 10:00",
        ])->json('data.id');
        $this->actingAs($agent)->postJson("/api/v1/requests/{$permission}/submit")->assertOk();
        $leave = $this->draft($agent, $leaveType);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$leave}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date' => sprintf('Cette période chevauche la demande OMIT-PM-%d-00001 (permission).', now()->year)]);
    }

    public function test_exit_pass_during_a_leave_returns_422(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $leave = $this->draft($agent, $leaveType, ['start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString()]);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$leave}/submit")->assertOk();
        $exit = $this->actingAs($agent)->postJson('/api/v1/exit-passes', [
            'departure_at' => now()->addHour()->toDateTimeString(), 'expected_return_at' => now()->addHours(2)->toDateTimeString(),
            'destination' => 'Banque', 'reason' => 'Démarche',
        ])->json('data.id');

        $this->actingAs($agent)->postJson("/api/v1/requests/{$exit}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('departure_at');
    }

    public function test_sick_leave_can_be_sent_without_proof_which_is_due_48_hours_after_the_first_day(): void
    {
        Storage::fake();
        $sickLeave = $this->sickLeave();
        $agent = User::factory()->forEmployee()->create();
        $start = now()->addDay()->startOfDay();
        $id = $this->draft($agent, $sickLeave, ['start_date' => $start->toDateString(), 'end_date' => $start->copy()->addDays(2)->toDateString()]);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.can_add_attachment', true);
        $this->assertEquals($start->copy()->addHours(48), EmployeeRequest::find($id)->leaveDetail->attachment_due_at);

        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/attachments", ['file' => UploadedFile::fake()->create('certificat.pdf', 50, 'application/pdf')])
            ->assertCreated();
    }

    public function test_missing_proof_is_reported_once_to_the_agent_and_rh_after_the_deadline(): void
    {
        $sickLeave = $this->sickLeave();
        $agent = User::factory()->forEmployee()->create();
        $rh = $this->rh();
        $id = $this->draft($agent, $sickLeave, ['start_date' => now()->toDateString(), 'end_date' => now()->addDays(2)->toDateString()]);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();
        Notification::fake();

        $this->artisan('omit:check-missing-attachments')->expectsOutputToContain('0 demande(s)')->assertSuccessful();
        $this->travel(49)->hours();
        $this->artisan('omit:check-missing-attachments')->expectsOutputToContain('1 demande(s)')->assertSuccessful();
        $this->artisan('omit:check-missing-attachments')->expectsOutputToContain('0 demande(s)')->assertSuccessful();

        Notification::assertSentToTimes($agent, RequestUpdate::class, 1);
        Notification::assertSentTo($rh, RequestUpdate::class, fn (RequestUpdate $notification): bool => $notification->title === 'Justificatif manquant');
    }

    public function test_proof_cannot_be_added_to_a_closed_request(): void
    {
        $sickLeave = $this->sickLeave();
        $agent = User::factory()->forEmployee()->create();
        $id = $this->draft($agent, $sickLeave);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();
        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/cancel")->assertOk();

        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/attachments", ['file' => UploadedFile::fake()->create('certificat.pdf', 50, 'application/pdf')])
            ->assertForbidden();
    }

    public function test_rh_set_notice_and_proof_deadline_on_a_leave_type(): void
    {
        $leaveType = $this->sickLeave();

        $this->actingAs($this->rh())->putJson("/api/v1/admin/leave-types/{$leaveType->id}", [
            'code' => $leaveType->code, 'name' => $leaveType->name, 'is_paid' => true, 'has_balance' => false,
            'requires_attachment' => true, 'is_medical' => true, 'allows_half_day' => false, 'is_active' => true,
            'min_notice_days' => 0, 'attachment_deadline_hours' => 72,
            'policy' => ['accrual_rule' => 'none', 'annual_entitlement' => 0, 'counting_mode' => 'calendar_excluding_holidays'],
        ])->assertOk()->assertJsonPath('data.attachment_deadline_hours', 72)->assertJsonPath('data.policy.counting_mode', 'calendar_excluding_holidays');
    }

    private function sickLeave(): LeaveType
    {
        return LeaveType::factory()->withoutBalance()->withPolicy(['accrual_rule' => 'none', 'annual_entitlement' => 0])
            ->create(['name' => 'Congé de maladie', 'requires_attachment' => true, 'is_medical' => true, 'attachment_deadline_hours' => 48]);
    }
}

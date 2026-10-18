<?php

namespace Tests\Feature\Api;

use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

class LeaveRequestControllerTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_draft_days_are_computed_by_the_server_and_client_value_is_ignored(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);

        $response = $this->actingAs($agent)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'return_date' => now()->addDays(15)->toDateString(),
            'interim_employee_id' => $this->interim()->id,
            'requested_days' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'brouillon')
            ->assertJsonPath('data.reference', null)
            ->assertJsonPath('data.leave.requested_days', 5)
            ->assertJsonPath('data.can_edit', true);
    }

    public function test_rh_without_employee_record_and_no_employee_id_gets_422(): void
    {
        $leaveType = $this->annualLeave();

        $this->actingAs($this->rh())->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_rh_can_create_and_submit_a_leave_request_for_any_employee(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $rh = $this->rh();

        $response = $this->actingAs($rh)->postJson('/api/v1/leave-requests', [
            'employee_id' => $agent->employee_id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'return_date' => now()->addDays(15)->toDateString(),
            'interim_employee_id' => $this->interim()->id,
        ])->assertCreated()->assertJsonPath('data.status', 'brouillon');

        $id = $response->json('data.id');
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'soumise');
    }

    public function test_period_with_only_non_counted_days_returns_422(): void
    {
        $leaveType = LeaveType::factory()->withPolicy(['counting_mode' => 'working'])->create();
        $agent = $this->agentWithBalance($leaveType);
        $saturday = now()->next('Saturday');

        $this->actingAs($agent)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => $saturday->toDateString(), 'end_date' => $saturday->copy()->addDay()->toDateString(),
            'return_date' => $saturday->copy()->addDays(2)->toDateString(), 'interim_employee_id' => $this->interim()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_agent_gets_403_on_another_agent_request_but_rh_can_read_it(): void
    {
        $leaveType = $this->annualLeave();
        $owner = $this->agentWithBalance($leaveType);
        $id = $this->draft($owner, $leaveType);
        $this->actingAs($owner)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();

        $this->actingAs(User::factory()->forEmployee()->create())->getJson("/api/v1/leave-requests/{$id}")->assertForbidden();
        $this->actingAs($this->rh())->getJson("/api/v1/leave-requests/{$id}")
            ->assertOk()
            ->assertJsonPath('data.balance.available', 10);
    }

    public function test_submitted_request_can_no_longer_be_edited_by_the_agent(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $id = $this->draft($agent, $leaveType);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();

        $this->actingAs($agent)->putJson("/api/v1/leave-requests/{$id}", [
            'leave_type_id' => $leaveType->id, 'start_date' => now()->addDays(20)->toDateString(), 'end_date' => now()->addDays(21)->toDateString(),
            'return_date' => now()->addDays(22)->toDateString(), 'interim_employee_id' => $this->interim()->id,
        ])->assertForbidden();
    }

    public function test_draft_can_be_deleted_but_not_a_submitted_request(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $draftId = $this->draft($agent, $leaveType);
        $submittedId = $this->draft($agent, $leaveType, ['start_date' => now()->addDays(30)->toDateString(), 'end_date' => now()->addDays(31)->toDateString()]);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$submittedId}/submit")->assertOk();

        $this->actingAs($agent)->deleteJson("/api/v1/leave-requests/{$draftId}")->assertOk();
        $this->actingAs($agent)->deleteJson("/api/v1/leave-requests/{$submittedId}")->assertForbidden();

        $this->assertDatabaseMissing('requests', ['id' => $draftId]);
        $this->assertDatabaseHas('requests', ['id' => $submittedId]);
    }

    public function test_index_lists_own_requests_and_all_scope_is_reserved_to_reviewers(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $this->draft($agent, $leaveType);
        EmployeeRequest::factory()->leave($leaveType)->status(RequestStatus::Soumise)->create();

        $this->actingAs($agent)->getJson('/api/v1/leave-requests')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($agent)->getJson('/api/v1/leave-requests?scope=all')->assertForbidden();
        $this->actingAs($this->rh())->getJson('/api/v1/leave-requests?scope=all')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'soumise');
    }
}

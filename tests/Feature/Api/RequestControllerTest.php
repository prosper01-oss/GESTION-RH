<?php

namespace Tests\Feature\Api;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\EmployeeRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

class RequestControllerTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_type_specific_lists_only_return_that_type(): void
    {
        $agent = User::factory()->forEmployee()->create();
        EmployeeRequest::factory()->for($agent->employee)->leave(LeaveType::factory()->withPolicy()->create())->create();
        EmployeeRequest::factory()->for($agent->employee)->create(['request_type' => RequestType::Sortie]);

        $this->actingAs($agent)->getJson('/api/v1/requests')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($agent)->getJson('/api/v1/leave-requests')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.request_type', 'conge');
        $this->actingAs($agent)->getJson('/api/v1/exit-passes')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.request_type', 'sortie');
    }

    public function test_reviewers_never_see_drafts_of_other_agents(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();
        EmployeeRequest::factory()->leave($leaveType)->create();
        EmployeeRequest::factory()->leave($leaveType)->status(RequestStatus::Soumise)->create();

        $this->actingAs($this->rh())->getJson('/api/v1/requests?scope=all')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_agent_gets_403_on_another_agent_request(): void
    {
        $request = EmployeeRequest::factory()->leave(LeaveType::factory()->withPolicy()->create())->status(RequestStatus::Soumise)->create();

        $this->actingAs(User::factory()->forEmployee()->create())->getJson("/api/v1/requests/{$request->id}")->assertForbidden();
    }
}

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

class RequestDocumentControllerTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_agent_downloads_the_pdf_authorization_of_an_approved_leave(): void
    {
        $request = EmployeeRequest::factory()->leave(LeaveType::factory()->withPolicy()->create())->status(RequestStatus::Finalisee)->create();
        $agent = User::factory()->forEmployee($request->employee)->create();

        $response = $this->actingAs($agent)->get("/api/v1/leave-requests/{$request->id}/pdf");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload("{$request->reference}.pdf");
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_exit_pass_pdf_is_available_once_approved(): void
    {
        $request = EmployeeRequest::factory()->status(RequestStatus::Approuvee)->create(['request_type' => RequestType::Sortie]);
        $request->exitDetail()->create([
            'departure_at' => now()->addHour(), 'expected_return_at' => now()->addHours(3), 'destination' => 'Banque', 'reason' => 'Démarche',
        ]);

        $this->actingAs($this->rh())->get("/api/v1/exit-passes/{$request->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_pending_request_has_no_document_yet(): void
    {
        $request = EmployeeRequest::factory()->leave(LeaveType::factory()->withPolicy()->create())->status(RequestStatus::Soumise)->create();

        $this->actingAs($this->rh())->getJson("/api/v1/requests/{$request->id}/pdf")->assertConflict();
    }

    public function test_another_agent_gets_403(): void
    {
        $request = EmployeeRequest::factory()->leave(LeaveType::factory()->withPolicy()->create())->status(RequestStatus::Finalisee)->create();

        $this->actingAs(User::factory()->forEmployee()->create())->getJson("/api/v1/requests/{$request->id}/pdf")->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

class RequestAttachmentControllerTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_required_attachment_blocks_submission_until_uploaded(): void
    {
        Storage::fake();
        $sickLeave = LeaveType::factory()->withoutBalance()->withPolicy(['accrual_rule' => 'none', 'annual_entitlement' => 0])->create(['requires_attachment' => true, 'is_medical' => true]);
        $agent = User::factory()->forEmployee()->create();
        $id = $this->draft($agent, $sickLeave);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachments' => 'Un justificatif est obligatoire pour ce type de congé.']);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/attachments", ['file' => UploadedFile::fake()->create('certificat.pdf', 200, 'application/pdf')])
            ->assertCreated();
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();
        $this->assertDatabaseHas('request_attachments', ['request_id' => $id, 'is_medical' => true, 'original_name' => 'certificat.pdf']);
    }

    public function test_medical_attachment_is_hidden_from_rh_visible_to_medecin_chef_and_access_is_logged(): void
    {
        Storage::fake();
        $sickLeave = LeaveType::factory()->withoutBalance()->withPolicy(['accrual_rule' => 'none', 'annual_entitlement' => 0])->create(['is_medical' => true]);
        $agent = User::factory()->forEmployee()->create();
        $id = $this->draft($agent, $sickLeave);
        $attachmentId = $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/attachments", ['file' => UploadedFile::fake()->create('certificat.pdf', 200, 'application/pdf')])->json('data.id');

        $this->actingAs($this->rh())->get("/api/v1/attachments/{$attachmentId}")->assertForbidden();
        $this->actingAs($agent)->get("/api/v1/attachments/{$attachmentId}")->assertOk();
        $this->actingAs($this->medecinChef())->get("/api/v1/attachments/{$attachmentId}")->assertOk()->assertDownload('certificat.pdf');

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attachment.medical_viewed', 'entity_id' => $id]);
    }

    public function test_executable_file_returns_422(): void
    {
        Storage::fake();
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $id = $this->draft($agent, $leaveType);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/attachments", ['file' => UploadedFile::fake()->create('script.php', 1, 'application/x-php')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Le justificatif doit être un PDF ou une image (JPG, PNG).']);
    }

    public function test_another_agent_cannot_attach_a_file(): void
    {
        Storage::fake();
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $id = $this->draft($agent, $leaveType);

        $this->actingAs(User::factory()->forEmployee()->create())
            ->postJson("/api/v1/leave-requests/{$id}/attachments", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Api;

use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use App\Models\PermissionType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

class PermissionRequestControllerTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_duration_is_computed_in_hours_by_the_server(): void
    {
        $type = PermissionType::factory()->create();
        $agent = User::factory()->forEmployee()->create();

        $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payload($type, '08:00', '11:30'))
            ->assertCreated()
            ->assertJsonPath('data.request_type', 'permission')
            ->assertJsonPath('data.permission.duration_hours', 3.5)
            ->assertJsonPath('data.permission.permission_type.id', $type->id);
    }

    public function test_full_circuit_then_automatic_closing_after_the_period(): void
    {
        $type = PermissionType::factory()->create();
        $agent = User::factory()->forEmployee()->create();
        $rh = $this->rh();
        $id = $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payload($type))->json('data.id');

        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.reference', sprintf('OMIT-PM-%d-00001', now()->year));
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'transmettre'])->assertOk();
        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertOk();
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/finalize")->assertOk();
        $this->travel(3)->days();

        $this->artisan('omit:close-finished-requests')->assertSuccessful();

        $this->assertSame(RequestStatus::Terminee, EmployeeRequest::find($id)->status);
    }

    public function test_duration_above_the_category_maximum_returns_422_on_submission(): void
    {
        $type = PermissionType::factory()->create(['max_duration_hours' => 4]);
        $agent = User::factory()->forEmployee()->create();
        $id = $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payload($type, '08:00', '13:00'))->json('data.id');

        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_at' => 'La durée dépasse le maximum autorisé pour cette catégorie (4 h).']);
    }

    public function test_medical_category_marks_attachments_as_medical_and_requires_one(): void
    {
        Storage::fake();
        $type = PermissionType::factory()->create(['requires_attachment' => true, 'is_medical' => true]);
        $agent = User::factory()->forEmployee()->create();
        $id = $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payload($type))->json('data.id');

        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/submit")->assertUnprocessable()->assertJsonValidationErrors('attachments');
        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/attachments", ['file' => UploadedFile::fake()->create('rdv.pdf', 50, 'application/pdf')])->assertCreated();

        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/submit")->assertOk();
        $this->assertDatabaseHas('request_attachments', ['request_id' => $id, 'is_medical' => true]);
    }

    public function test_hr_attaches_the_required_document_to_a_permission_filed_for_an_agent(): void
    {
        Storage::fake();
        $type = PermissionType::factory()->create(['requires_attachment' => true]);
        $agent = User::factory()->forEmployee()->create();
        $rh = $this->rh();
        $id = $this->actingAs($rh)->postJson('/api/v1/permission-requests', ['employee_id' => $agent->employee->id] + $this->payload($type))->json('data.id');

        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/attachments", ['file' => UploadedFile::fake()->create('acte.pdf', 50, 'application/pdf')])->assertCreated();
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/submit")->assertOk();

        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/attachments", ['file' => UploadedFile::fake()->create('autre.pdf', 50, 'application/pdf')])->assertForbidden();
    }

    public function test_overlapping_permission_returns_422(): void
    {
        $type = PermissionType::factory()->create();
        $agent = User::factory()->forEmployee()->create();
        $first = $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payload($type, '08:00', '10:00'))->json('data.id');
        $second = $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payload($type, '09:00', '11:00'))->json('data.id');
        $this->actingAs($agent)->postJson("/api/v1/requests/{$first}/submit")->assertOk();

        $this->actingAs($agent)->postJson("/api/v1/requests/{$second}/submit")->assertUnprocessable()->assertJsonValidationErrors('start_at');
    }

    public function test_other_category_requires_details_which_become_the_displayed_category(): void
    {
        $other = PermissionType::where('name', 'Autres (à préciser)')->sole();
        $agent = User::factory()->forEmployee()->create();

        $this->actingAs($agent)->postJson('/api/v1/permission-requests', ['reason' => ''] + $this->payload($other))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason' => 'Précisez la nature de la permission.']);

        $id = $this->actingAs($agent)->postJson('/api/v1/permission-requests', ['reason' => "Funérailles d'un proche"] + $this->payload($other))
            ->assertCreated()
            ->assertJsonPath('data.permission.permission_type.requires_details', true)
            ->assertJsonPath('data.permission.category_label', "Autres : Funérailles d'un proche")
            ->json('data.id');
        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/submit")->assertOk();
    }

    public function test_categories_to_specify_come_last_in_the_list(): void
    {
        PermissionType::factory()->create(['name' => 'Mariage Salarié']);

        $names = $this->actingAs(User::factory()->forEmployee()->create())->getJson('/api/v1/permission-types')->assertOk()->json('data.*.name');

        $this->assertSame('Autres (à préciser)', end($names));
    }

    public function test_regular_category_keeps_the_reason_optional(): void
    {
        $type = PermissionType::factory()->create(['name' => 'Rendez-vous médical']);

        $this->actingAs(User::factory()->forEmployee()->create())->postJson('/api/v1/permission-requests', ['reason' => ''] + $this->payload($type))
            ->assertCreated()
            ->assertJsonPath('data.permission.category_label', 'Rendez-vous médical');
    }

    public function test_end_before_start_returns_422(): void
    {
        $type = PermissionType::factory()->create();

        $this->actingAs(User::factory()->forEmployee()->create())
            ->postJson('/api/v1/permission-requests', $this->payload($type, '11:00', '09:00'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_at' => 'La fin doit suivre le début.']);
    }

    public function test_each_date_counts_a_half_day_under_four_hours_and_a_full_day_otherwise(): void
    {
        $type = PermissionType::factory()->create();
        $agent = User::factory()->forEmployee()->create();

        $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payload($type, '08:00', '11:30'))
            ->assertJsonPath('data.permission.counted_days', 0.5);
        $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payload($type, '08:00', '12:00'))
            ->assertJsonPath('data.permission.counted_days', 1);

        $start = now()->addDays(2)->toDateString();
        $end = now()->addDays(4)->toDateString();
        $this->actingAs($agent)->postJson('/api/v1/permission-requests', ['permission_type_id' => $type->id, 'start_at' => "{$start} 08:00", 'end_at' => "{$end} 10:00"])
            ->assertJsonPath('data.permission.counted_days', 2.5);
    }

    public function test_submission_beyond_the_annual_quota_returns_422(): void
    {
        config(['omit.permission_annual_days' => 1]);
        $this->travelTo('2026-03-02 09:00');
        $type = PermissionType::factory()->create();
        $agent = User::factory()->forEmployee()->create();
        $first = $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payloadOn($type, '2026-03-04', '08:00', '12:00'))->json('data.id');
        $second = $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payloadOn($type, '2026-03-05', '08:00', '10:00'))->json('data.id');

        $this->actingAs($agent)->postJson("/api/v1/requests/{$first}/submit")->assertOk();

        $this->actingAs($agent)->postJson("/api/v1/requests/{$second}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_at' => 'Quota de permissions 2026 dépassé : cette demande compte 0,5 j, il reste 0 j sur 1 j.']);
    }

    public function test_quota_starts_again_each_new_year_without_carry_over(): void
    {
        config(['omit.permission_annual_days' => 1]);
        $this->travelTo('2026-12-20 09:00');
        $type = PermissionType::factory()->create();
        $agent = User::factory()->forEmployee()->create();
        $december = $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payloadOn($type, '2026-12-22', '08:00', '12:00'))->json('data.id');
        $january = $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payloadOn($type, '2027-01-05', '08:00', '12:00'))->json('data.id');

        $this->actingAs($agent)->postJson("/api/v1/requests/{$december}/submit")->assertOk();
        $this->actingAs($agent)->postJson("/api/v1/requests/{$january}/submit")->assertOk();

        $this->actingAs($agent)->getJson('/api/v1/permission-quota/me?year=2026')
            ->assertOk()
            ->assertJson(['data' => ['year' => 2026, 'entitlement' => 1, 'used' => 1, 'remaining' => 0]]);
        $this->actingAs($agent)->getJson('/api/v1/permission-quota/me?year=2028')
            ->assertJson(['data' => ['year' => 2028, 'entitlement' => 1, 'used' => 0, 'remaining' => 1]]);
    }

    public function test_drafts_and_cancelled_requests_do_not_use_the_quota(): void
    {
        $type = PermissionType::factory()->create();
        $agent = User::factory()->forEmployee()->create();
        $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payload($type));
        $id = $this->actingAs($agent)->postJson('/api/v1/permission-requests', $this->payload($type, '13:00', '17:00'))->json('data.id');
        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/submit")->assertOk();
        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/cancel")->assertOk();

        $this->actingAs($agent)->getJson('/api/v1/permission-quota/me')
            ->assertJson(['data' => ['entitlement' => 10, 'used' => 0, 'remaining' => 10]]);
    }

    public function test_an_agent_cannot_read_a_colleague_quota(): void
    {
        $colleague = User::factory()->forEmployee()->create();

        $this->actingAs(User::factory()->forEmployee()->create())
            ->getJson("/api/v1/permission-quota/{$colleague->employee->id}")
            ->assertForbidden();
        $this->actingAs($this->rh())
            ->getJson("/api/v1/permission-quota/{$colleague->employee->id}")
            ->assertOk()
            ->assertJsonPath('data.remaining', 10);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadOn(PermissionType $type, string $day, string $from, string $to): array
    {
        return ['permission_type_id' => $type->id, 'start_at' => "{$day} {$from}", 'end_at' => "{$day} {$to}"];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PermissionType $type, string $from = '08:00', string $to = '12:00'): array
    {
        $day = now()->addDays(2)->toDateString();

        return ['permission_type_id' => $type->id, 'start_at' => "{$day} {$from}", 'end_at' => "{$day} {$to}", 'reason' => 'Démarche administrative'];
    }
}

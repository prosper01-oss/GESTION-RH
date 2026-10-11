<?php

namespace Tests\Feature\Api;

use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

class ExitPassControllerTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_departure_and_return_close_the_exit_pass(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        $agent = User::factory()->forEmployee()->create();
        $rh = $this->rh();
        $id = $this->finalizedExitPass($agent, $rh);

        $this->actingAs($rh)->postJson("/api/v1/exit-passes/{$id}/record-departure")
            ->assertOk()
            ->assertJsonPath('data.exit_pass.actual_departure_at', now()->toIso8601ZuluString('microsecond'));
        $this->travel(2)->hours();
        $this->actingAs($rh)->postJson("/api/v1/exit-passes/{$id}/record-return")
            ->assertOk()
            ->assertJsonPath('data.status', 'terminee');

        $this->assertNotNull(EmployeeRequest::find($id)->exitDetail->actual_return_at);
        $this->assertSame(sprintf('OMIT-BS-%d-00001', now()->year), EmployeeRequest::find($id)->reference);
    }

    public function test_overdue_exits_are_listed_for_follow_up(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        $agent = User::factory()->forEmployee()->create();
        $rh = $this->rh();
        $id = $this->finalizedExitPass($agent, $rh);
        $this->actingAs($rh)->postJson("/api/v1/exit-passes/{$id}/record-departure")->assertOk();

        $this->actingAs($rh)->getJson('/api/v1/exit-passes?scope=all&state=en_retard')->assertOk()->assertJsonCount(0, 'data');
        $this->travel(5)->hours();

        $this->actingAs($rh)->getJson('/api/v1/exit-passes?scope=all&state=en_retard')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.exit_pass.is_overdue', true);
    }

    public function test_exit_pass_cannot_be_closed_without_a_recorded_return(): void
    {
        $agent = User::factory()->forEmployee()->create();
        $rh = $this->rh();
        $id = $this->finalizedExitPass($agent, $rh);

        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/complete")->assertConflict();
        $this->actingAs($rh)->postJson("/api/v1/exit-passes/{$id}/record-return")->assertConflict();
    }

    public function test_exit_pass_cannot_be_cancelled_after_departure(): void
    {
        $agent = User::factory()->forEmployee()->create();
        $rh = $this->rh();
        $id = $this->finalizedExitPass($agent, $rh);
        $this->actingAs($rh)->postJson("/api/v1/exit-passes/{$id}/record-departure")->assertOk();

        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/cancel")->assertConflict();
        $this->assertSame(RequestStatus::Finalisee, EmployeeRequest::find($id)->status);
    }

    public function test_agent_cannot_record_own_departure(): void
    {
        $agent = User::factory()->forEmployee()->create();
        $id = $this->finalizedExitPass($agent, $this->rh());

        $this->actingAs($agent)->postJson("/api/v1/exit-passes/{$id}/record-departure")->assertForbidden();
    }

    public function test_missing_destination_and_reason_return_422(): void
    {
        $this->actingAs(User::factory()->forEmployee()->create())->postJson('/api/v1/exit-passes', [
            'departure_at' => now()->addHour()->toDateTimeString(),
            'expected_return_at' => now()->addHours(3)->toDateTimeString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['destination', 'reason']);
    }

    private function finalizedExitPass(User $agent, User $rh): int
    {
        $id = $this->actingAs($agent)->postJson('/api/v1/exit-passes', [
            'departure_at' => now()->addMinutes(30)->toDateTimeString(),
            'expected_return_at' => now()->addHours(3)->toDateTimeString(),
            'destination' => 'Banque, centre-ville',
            'reason' => 'Démarche bancaire',
        ])->assertCreated()->json('data.id');

        $this->actingAs($agent)->postJson("/api/v1/requests/{$id}/submit")->assertOk();
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'transmettre'])->assertOk();
        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertOk();
        $this->actingAs($rh)->postJson("/api/v1/requests/{$id}/finalize")->assertOk();

        return $id;
    }
}

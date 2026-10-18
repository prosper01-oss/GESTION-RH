<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\RequestUpdate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

/**
 * Date de reprise du travail et intérim, obligatoires pour toute demande de congé.
 */
class LeaveReturnAndInterimTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_return_date_and_interim_are_required(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);

        $this->actingAs($agent)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(14)->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'return_date' => 'Indiquez la date de reprise du travail.',
            'interim_employee_id' => "Choisissez l'agent qui assure l'intérim du poste.",
        ]);
    }

    public function test_return_date_cannot_precede_the_last_day(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);

        $this->actingAs($agent)->postJson('/api/v1/leave-requests', $this->payload($leaveType, ['return_date' => now()->addDays(13)->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['return_date' => 'La date de reprise ne peut pas précéder le dernier jour de congé.']);
    }

    public function test_return_on_the_last_day_requires_a_morning_end(): void
    {
        $leaveType = $this->annualLeave(['allows_half_day' => true]);
        $agent = $this->agentWithBalance($leaveType);
        $lastDay = now()->addDays(14)->toDateString();

        $this->actingAs($agent)->postJson('/api/v1/leave-requests', $this->payload($leaveType, ['return_date' => $lastDay]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('return_date');
        $this->actingAs($agent)->postJson('/api/v1/leave-requests', $this->payload($leaveType, ['return_date' => $lastDay, 'ends_morning' => true]))
            ->assertCreated()
            ->assertJsonPath('data.leave.return_date', $lastDay);
    }

    public function test_interim_cannot_be_the_requester_nor_an_inactive_agent(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);

        $this->actingAs($agent)->postJson('/api/v1/leave-requests', $this->payload($leaveType, ['interim_employee_id' => $agent->employee_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['interim_employee_id' => "L'intérimaire doit être un autre agent que le demandeur."]);
        $this->actingAs($agent)->postJson('/api/v1/leave-requests', $this->payload($leaveType, ['interim_employee_id' => Employee::factory()->inactive()->create()->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['interim_employee_id' => "L'intérimaire doit être un agent actif."]);
    }

    public function test_interim_absent_during_the_leave_is_refused_at_submission(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $colleague = $this->agentWithBalance($leaveType);
        $colleagueLeave = $this->draft($colleague, $leaveType);
        $this->actingAs($colleague)->postJson("/api/v1/leave-requests/{$colleagueLeave}/submit")->assertOk();
        $id = $this->draft($agent, $leaveType, ['interim_employee_id' => $colleague->employee_id]);

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('interim_employee_id');
    }

    public function test_request_shows_the_interim_and_the_interim_is_notified_on_approval(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $interimUser = User::factory()->forEmployee($this->interim())->create();
        $id = $this->draft($agent, $leaveType);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.leave.interim.id', $interimUser->employee_id)
            ->assertJsonPath('data.leave.return_date', now()->addDays(15)->toDateString());
        $this->actingAs($this->rh())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'transmettre'])->assertOk();
        Notification::fake();

        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$id}/decision", ['decision' => 'approuver'])->assertOk();

        Notification::assertSentTo($interimUser, RequestUpdate::class, fn (RequestUpdate $notification): bool => $notification->title === 'Intérim à assurer'
            && str_contains($notification->message, 'reprise le '.now()->addDays(15)->format('d/m/Y')));
    }

    public function test_any_agent_can_search_active_colleagues_except_themselves(): void
    {
        $agent = User::factory()->forEmployee(Employee::factory()->create(['nom' => 'Rakoto']))->create();
        Employee::factory()->create(['nom' => 'Rakotobe']);
        Employee::factory()->inactive()->create(['nom' => 'Rakotomalala']);

        $this->actingAs($agent)->getJson('/api/v1/colleagues?search=Rakoto')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Rakotobe');
        $this->actingAs($agent)->getJson('/api/v1/colleagues?search=R')->assertUnprocessable();
        $this->actingAs($this->userWithRole(RoleName::Personnel))->getJson('/api/v1/employees?search=Rakoto')->assertForbidden();
    }

    public function test_colleague_search_requires_a_session(): void
    {
        $this->getJson('/api/v1/colleagues?search=Rakoto')->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(LeaveType $leaveType, array $overrides = []): array
    {
        return $overrides + [
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'return_date' => now()->addDays(15)->toDateString(),
            'interim_employee_id' => $this->interim()->id,
        ];
    }
}

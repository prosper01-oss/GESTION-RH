<?php

namespace Tests\Feature\Notifications;

use App\Contracts\SmsGateway;
use App\Enums\RequestStatus;
use App\Enums\RoleName;
use App\Models\Delegation;
use App\Models\EmployeeRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\PendingRequestsReminder;
use App\Notifications\RequestUpdate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesLeaveRequests;
use Tests\TestCase;

class RequestNotificationsTest extends TestCase
{
    use CreatesLeaveRequests, LazilyRefreshDatabase;

    public function test_submission_notifies_rh_but_not_the_author(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $rh = $this->rh();
        $id = $this->draft($agent, $leaveType);
        Notification::fake();

        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();

        Notification::assertSentTo($rh, RequestUpdate::class, fn (RequestUpdate $notification, array $channels): bool => $notification->title === 'Demande à contrôler'
            && $channels === ['database', 'mail']);
        Notification::assertNotSentTo($agent, RequestUpdate::class);
    }

    public function test_transmission_notifies_the_medecin_chef_and_current_delegates_by_sms(): void
    {
        $medecinChef = $this->medecinChef();
        $delegate = User::factory()->forEmployee()->create();
        Delegation::create(['delegator_id' => $medecinChef->id, 'delegate_id' => $delegate->id, 'role' => RoleName::MedecinChef, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString()]);
        $request = EmployeeRequest::factory()->leave(LeaveType::factory()->withoutBalance()->withPolicy()->create())->status(RequestStatus::Soumise)->create();
        $rh = $this->rh();
        Notification::fake();

        $this->actingAs($rh)->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'transmettre'])->assertOk();

        foreach ([$medecinChef, $delegate] as $user) {
            Notification::assertSentTo($user, RequestUpdate::class, fn (RequestUpdate $notification, array $channels): bool => in_array(SmsChannel::class, $channels, true));
        }
    }

    public function test_refusal_notifies_the_agent_with_the_reason_and_respects_preferences(): void
    {
        $request = EmployeeRequest::factory()->leave(LeaveType::factory()->withoutBalance()->withPolicy()->create())->status(RequestStatus::TransmiseMedecin)->create();
        $agent = User::factory()->forEmployee($request->employee)->create(['notify_by_email' => false, 'notify_by_sms' => true]);
        Notification::fake();

        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'refuser', 'comment' => 'Effectif insuffisant'])->assertOk();

        Notification::assertSentTo($agent, RequestUpdate::class, fn (RequestUpdate $notification, array $channels): bool => str_contains($notification->message, 'Motif : Effectif insuffisant')
            && $channels === ['database', SmsChannel::class]);
    }

    public function test_sms_goes_to_the_professional_phone_through_the_gateway(): void
    {
        $gateway = new class implements SmsGateway
        {
            /** @var list<array{string, string}> */
            public array $sent = [];

            public function send(string $phoneNumber, string $message): void
            {
                $this->sent[] = [$phoneNumber, $message];
            }
        };
        $this->app->instance(SmsGateway::class, $gateway);
        $request = EmployeeRequest::factory()->leave(LeaveType::factory()->withoutBalance()->withPolicy()->create())->status(RequestStatus::TransmiseMedecin)->create();
        $request->employee->update(['phone_pro' => '+261 34 11 222 33']);
        User::factory()->forEmployee($request->employee)->create();

        $this->actingAs($this->medecinChef())->postJson("/api/v1/requests/{$request->id}/decision", ['decision' => 'approuver'])->assertOk();

        $this->assertCount(1, $gateway->sent);
        $this->assertSame('+261 34 11 222 33', $gateway->sent[0][0]);
        $this->assertStringContainsString('est approuvée', $gateway->sent[0][1]);
    }

    public function test_in_app_notification_is_stored_and_can_be_read(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $rh = $this->rh();
        $id = $this->draft($agent, $leaveType);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();

        $response = $this->actingAs($rh)->getJson('/api/v1/me/notifications');

        $response->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.title', 'Demande à contrôler')
            ->assertJsonPath('data.0.link', "/demandes/{$id}");
        $this->actingAs($rh)->patchJson('/api/v1/me/notifications/'.$response->json('data.0.id').'/read')->assertOk();
        $this->actingAs($rh)->getJson('/api/v1/me/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_user_cannot_read_another_user_notification(): void
    {
        $leaveType = $this->annualLeave();
        $agent = $this->agentWithBalance($leaveType);
        $this->rh();
        $id = $this->draft($agent, $leaveType);
        $this->actingAs($agent)->postJson("/api/v1/leave-requests/{$id}/submit")->assertOk();
        $notificationId = DatabaseNotification::firstOrFail()->id;

        $this->actingAs($agent)->patchJson("/api/v1/me/notifications/{$notificationId}/read")->assertNotFound();
    }

    public function test_preferences_are_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/me/notification-preferences', ['notify_by_email' => false, 'notify_by_sms' => false])
            ->assertOk()
            ->assertJsonPath('data.notify_by_email', false);
        $this->assertFalse($user->fresh()->notify_by_sms);
    }

    public function test_reminder_targets_stale_requests_only_and_never_the_author(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();
        $rh = $this->rh();
        $rhAgent = User::factory()->forEmployee()->withRole(RoleName::RessourcesHumaines)->create();
        $stale = EmployeeRequest::factory()->leave($leaveType)->status(RequestStatus::Soumise)->create();
        $ownStale = EmployeeRequest::factory()->for($rhAgent->employee)->leave($leaveType)->status(RequestStatus::Soumise)->create();
        EmployeeRequest::query()->whereKey([$stale->id, $ownStale->id])->update(['updated_at' => now()->subDays(5)]);
        EmployeeRequest::factory()->leave($leaveType)->status(RequestStatus::Soumise)->create();
        Notification::fake();

        $this->artisan('omit:remind-pending-requests')->assertSuccessful();

        Notification::assertSentTo($rh, PendingRequestsReminder::class, fn (PendingRequestsReminder $notification): bool => count($notification->references) === 2);
        Notification::assertSentTo($rhAgent, PendingRequestsReminder::class, fn (PendingRequestsReminder $notification): bool => $notification->references === [$stale->reference]);
    }
}

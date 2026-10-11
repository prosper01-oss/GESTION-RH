<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credentials_open_a_session_and_return_roles_and_permissions(): void
    {
        $this->freezeSecond();
        $user = User::factory()->withRole(RoleName::RessourcesHumaines)->create(['password' => 'mot-de-passe-1']);

        $response = $this->login($user->email, 'mot-de-passe-1');

        $response->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles', ['rh'])
            ->assertJsonPath('data.permissions', ['employees.view', 'employees.manage', 'departments.manage', 'leave_settings.manage', 'leave_balances.view', 'leave_balances.adjust', 'requests.review']);
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertEquals(now(), $user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_returns_422_and_logs_the_failure(): void
    {
        $user = User::factory()->create(['password' => 'mot-de-passe-1']);

        $response = $this->login($user->email, 'mauvais');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Adresse email ou mot de passe incorrect.']);
        $this->assertGuest('web');
        $this->assertSame(['email' => $user->email], AuditLog::where('action', 'auth.login_failed')->sole()->new_values);
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'mot-de-passe-1']);

        $response = $this->login($user->email, 'mot-de-passe-1');

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Ce compte est désactivé.']);
        $this->assertGuest('web');
    }

    public function test_sixth_attempt_within_a_minute_returns_429(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->login($user->email, 'mauvais')->assertUnprocessable();
        }

        $this->login($user->email, 'mauvais')->assertTooManyRequests();
    }

    public function test_logout_closes_the_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->withHeader('Referer', config('app.url'))->postJson('/api/v1/logout');

        $response->assertOk();
        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_login_without_a_frontend_session_returns_400(): void
    {
        $user = User::factory()->create(['password' => 'mot-de-passe-1']);

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'mot-de-passe-1'])->assertBadRequest();
        $this->assertGuest('web');
    }

    public function test_me_returns_401_without_a_session(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_deactivated_account_is_logged_out_on_its_next_request(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/me');

        $response->assertUnauthorized()->assertJsonPath('message', 'Ce compte est désactivé.');
        $this->assertGuest('web');
    }

    /**
     * Connexion depuis le frontend : l'en-tête Referer active la session Sanctum.
     */
    private function login(string $email, string $password): TestResponse
    {
        return $this->withHeader('Referer', config('app.url'))
            ->postJson('/api/v1/login', ['email' => $email, 'password' => $password]);
    }
}

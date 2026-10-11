<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MyPasswordControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_temporary_password_blocks_other_routes_with_403(): void
    {
        $user = User::factory()->mustChangePassword()->withRole(RoleName::RessourcesHumaines)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/employees');

        $response->assertForbidden()->assertJsonPath('code', 'password_change_required');
    }

    public function test_temporary_password_still_allows_reading_the_profile(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);
    }

    public function test_changing_the_password_lifts_the_restriction(): void
    {
        $user = User::factory()->mustChangePassword()->create(['password' => 'provisoire1']);

        $response = $this->actingAs($user)->patchJson('/api/v1/me/password', [
            'current_password' => 'provisoire1',
            'password' => 'nouveau-secret-42',
            'password_confirmation' => 'nouveau-secret-42',
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('nouveau-secret-42', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_changed', 'entity_id' => $user->id]);
    }

    public function test_wrong_current_password_returns_422(): void
    {
        $user = User::factory()->create(['password' => 'actuel-123']);

        $response = $this->actingAs($user)->patchJson('/api/v1/me/password', [
            'current_password' => 'faux',
            'password' => 'nouveau-secret-42',
            'password_confirmation' => 'nouveau-secret-42',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check('actuel-123', $user->fresh()->password));
    }

    public function test_password_without_digits_returns_422(): void
    {
        $user = User::factory()->create(['password' => 'actuel-123']);

        $response = $this->actingAs($user)->patchJson('/api/v1/me/password', [
            'current_password' => 'actuel-123',
            'password' => 'seulementdeslettres',
            'password_confirmation' => 'seulementdeslettres',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['password' => 'Le champ mot de passe doit contenir au moins un chiffre.']);
    }

    public function test_password_identical_to_the_current_one_returns_a_french_message(): void
    {
        $user = User::factory()->create(['password' => 'actuel-123']);

        $response = $this->actingAs($user)->patchJson('/api/v1/me/password', [
            'current_password' => 'actuel-123',
            'password' => 'actuel-123',
            'password_confirmation' => 'actuel-123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['password' => "Le nouveau mot de passe doit être différent de l'actuel."]);
    }
}

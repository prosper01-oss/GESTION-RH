<?php

namespace Tests\Feature\Console;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CreateAdministratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_an_administrator_who_must_change_the_password(): void
    {
        $this->artisan('omit:create-admin', ['email' => 'admin@omit.mg'])->assertSuccessful();

        $admin = User::where('email', 'admin@omit.mg')->sole();
        $this->assertTrue($admin->hasRole(RoleName::Administrateur));
        $this->assertTrue($admin->must_change_password);
    }

    public function test_existing_email_fails_without_creating_an_account(): void
    {
        User::factory()->create(['email' => 'admin@omit.mg']);

        $this->artisan('omit:create-admin', ['email' => 'admin@omit.mg'])->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }
}

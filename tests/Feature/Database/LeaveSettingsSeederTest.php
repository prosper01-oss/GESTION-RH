<?php

namespace Tests\Feature\Database;

use Database\Seeders\LeaveSettingsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeaveSettingsSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_running_the_seeder_twice_does_not_duplicate_settings(): void
    {
        $this->seed(LeaveSettingsSeeder::class);
        $this->seed(LeaveSettingsSeeder::class);

        $this->assertDatabaseCount('leave_types', 3);
        $this->assertDatabaseCount('leave_policies', 3);
        $this->assertDatabaseCount('permission_types', 4);
        $this->assertDatabaseCount('public_holidays', 8);
    }
}

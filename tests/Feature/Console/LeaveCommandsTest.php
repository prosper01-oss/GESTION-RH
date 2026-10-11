<?php

namespace Tests\Feature\Console;

use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeaveCommandsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accrue_leave_credits_the_previous_month_by_default(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1));
        LeaveType::factory()->withPolicy()->create();
        Employee::factory()->create(['hire_date' => '2020-01-15']);

        $this->artisan('omit:accrue-leave')->expectsOutputToContain('pour 09/2026')->assertSuccessful();

        $this->assertDatabaseHas('leave_balance_entries', ['entry_type' => 'acquisition', 'reference_period' => '2026-09']);
    }

    public function test_accrue_leave_rejects_a_malformed_month(): void
    {
        $this->artisan('omit:accrue-leave', ['--month' => '2026-13'])->assertFailed();
    }

    public function test_close_leave_year_rejects_a_future_year(): void
    {
        $this->artisan('omit:close-leave-year', ['year' => now()->year + 1])->assertFailed();
    }
}

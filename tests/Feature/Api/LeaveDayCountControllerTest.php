<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeaveDayCountControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_counts_days_with_the_type_rules(): void
    {
        $leaveType = LeaveType::factory()->withPolicy(['counting_mode' => 'working'])->create(['allows_half_day' => true]);

        $this->actingAs($this->userWithRole(RoleName::Personnel))
            ->getJson("/api/v1/leave-days/count?leave_type_id={$leaveType->id}&start_date=2026-10-05&end_date=2026-10-09&ends_morning=1")
            ->assertOk()
            ->assertJsonPath('data.days', 4.5)
            ->assertJsonPath('data.counting_mode', 'working');
    }

    public function test_half_day_flags_are_ignored_when_the_type_does_not_allow_them(): void
    {
        $leaveType = LeaveType::factory()->withPolicy(['counting_mode' => 'calendar'])->create(['allows_half_day' => false]);

        $this->actingAs($this->userWithRole(RoleName::Personnel))
            ->getJson("/api/v1/leave-days/count?leave_type_id={$leaveType->id}&start_date=2026-10-05&end_date=2026-10-09&ends_morning=1")
            ->assertOk()
            ->assertJsonPath('data.days', 5);
    }

    public function test_end_before_start_returns_422(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();

        $this->actingAs($this->userWithRole(RoleName::Personnel))
            ->getJson("/api/v1/leave-days/count?leave_type_id={$leaveType->id}&start_date=2026-10-09&end_date=2026-10-05")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }
}

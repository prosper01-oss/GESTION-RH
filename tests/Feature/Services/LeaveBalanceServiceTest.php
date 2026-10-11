<?php

namespace Tests\Feature\Services;

use App\Enums\AccrualRule;
use App\Enums\BalanceEntryType;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeaveBalanceServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_monthly_accrual_credits_one_twelfth_of_the_annual_entitlement(): void
    {
        $leaveType = LeaveType::factory()->withPolicy(['annual_entitlement' => 30])->create();
        $employee = Employee::factory()->create(['hire_date' => '2020-01-15']);

        $created = $this->service()->accrueMonth(CarbonImmutable::parse('2026-09-01'));

        $this->assertSame(1, $created);
        $this->assertSame(2.5, $this->available($employee, $leaveType, 2026));
    }

    public function test_monthly_accrual_is_idempotent(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();
        $employee = Employee::factory()->create(['hire_date' => '2020-01-15']);

        $this->service()->accrueMonth(CarbonImmutable::parse('2026-09-01'));
        $second = $this->service()->accrueMonth(CarbonImmutable::parse('2026-09-20'));

        $this->assertSame(0, $second);
        $this->assertSame(2.5, $this->available($employee, $leaveType, 2026));
    }

    public function test_monthly_accrual_skips_inactive_employees_and_those_hired_after_the_first_of_the_month(): void
    {
        LeaveType::factory()->withPolicy()->create();
        Employee::factory()->inactive()->create(['hire_date' => '2020-01-15']);
        Employee::factory()->create(['hire_date' => '2026-09-02']);
        $hiredOnTheFirst = Employee::factory()->create(['hire_date' => '2026-09-01']);

        $created = $this->service()->accrueMonth(CarbonImmutable::parse('2026-09-01'));

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('leave_balances', ['employee_id' => $hiredOnTheFirst->id]);
    }

    public function test_monthly_accrual_ignores_types_without_balance_or_with_another_rule(): void
    {
        LeaveType::factory()->withoutBalance()->withPolicy()->create();
        LeaveType::factory()->withPolicy(['accrual_rule' => AccrualRule::Annuelle])->create();
        LeaveType::factory()->inactive()->withPolicy()->create();
        Employee::factory()->create(['hire_date' => '2020-01-15']);

        $this->assertSame(0, $this->service()->accrueMonth(CarbonImmutable::parse('2026-09-01')));
    }

    public function test_accrual_uses_the_rules_in_force_during_the_month(): void
    {
        $leaveType = LeaveType::factory()->withPolicy(['annual_entitlement' => 24])->create();
        $leaveType->policies()->create([
            'accrual_rule' => AccrualRule::Mensuelle, 'annual_entitlement' => 36, 'counting_mode' => 'calendar', 'effective_from' => '2026-10-01',
        ]);
        $employee = Employee::factory()->create(['hire_date' => '2020-01-15']);

        $this->service()->accrueMonth(CarbonImmutable::parse('2026-09-01'));
        $this->service()->accrueMonth(CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(5.0, $this->available($employee, $leaveType, 2026));
    }

    public function test_closing_a_year_carries_over_within_the_limit_and_expires_the_rest(): void
    {
        $leaveType = LeaveType::factory()->withPolicy(['carry_over_max' => 10])->create();
        $employee = Employee::factory()->create(['hire_date' => '2020-01-15']);
        $this->service()->adjust($employee, $leaveType, 2025, 14, 'Reprise des soldes papier', $this->rh());

        $result = $this->service()->closeYear(2025);

        $this->assertSame(['carried_over' => 1, 'expired' => 1, 'annual_credits' => 0], $result);
        $this->assertSame(0.0, $this->available($employee, $leaveType, 2025));
        $this->assertSame(10.0, $this->summary($employee, $leaveType, 2026)['carried_over']);
        $this->assertSame(4.0, $this->summary($employee, $leaveType, 2025)['expired']);
    }

    public function test_closing_a_year_without_limit_carries_over_everything(): void
    {
        $leaveType = LeaveType::factory()->withPolicy(['carry_over_max' => null])->create();
        $employee = Employee::factory()->create(['hire_date' => '2020-01-15']);
        $this->service()->adjust($employee, $leaveType, 2025, 14, 'Reprise des soldes papier', $this->rh());

        $this->service()->closeYear(2025);

        $this->assertSame(14.0, $this->available($employee, $leaveType, 2026));
    }

    public function test_closing_a_year_twice_does_not_carry_over_twice(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();
        $employee = Employee::factory()->create(['hire_date' => '2020-01-15']);
        $this->service()->adjust($employee, $leaveType, 2025, 6, 'Reprise des soldes papier', $this->rh());

        $this->service()->closeYear(2025);
        $second = $this->service()->closeYear(2025);

        $this->assertSame(0, $second['carried_over']);
        $this->assertSame(6.0, $this->available($employee, $leaveType, 2026));
    }

    public function test_closing_a_year_credits_annual_entitlements_of_the_next_year(): void
    {
        $leaveType = LeaveType::factory()->withPolicy(['accrual_rule' => AccrualRule::Annuelle, 'annual_entitlement' => 18])->create();
        $employee = Employee::factory()->create(['hire_date' => '2020-01-15']);

        $result = $this->service()->closeYear(2025);
        $this->service()->closeYear(2025);

        $this->assertSame(1, $result['annual_credits']);
        $this->assertSame(18.0, $this->available($employee, $leaveType, 2026));
    }

    public function test_summary_separates_movements_and_sums_the_available_balance(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();
        $employee = Employee::factory()->create();
        $balance = LeaveBalance::create(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'period_year' => 2026]);
        foreach ([
            [BalanceEntryType::Acquisition, 25], [BalanceEntryType::Report, 3], [BalanceEntryType::Consommation, -8],
            [BalanceEntryType::Reservation, -5], [BalanceEntryType::AnnulationReservation, 2], [BalanceEntryType::Correction, -1.5],
        ] as [$type, $days]) {
            $balance->entries()->create(['entry_type' => $type, 'days' => $days]);
        }

        $summary = $this->service()->summary($balance);

        $this->assertSame([
            'acquired' => 25.0, 'carried_over' => 3.0, 'consumed' => 8.0, 'reserved' => 3.0,
            'adjusted' => -1.5, 'expired' => 0.0, 'available' => 15.5,
        ], $summary);
    }

    public function test_summary_never_returns_negative_zero(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();
        $employee = Employee::factory()->create();
        $balance = LeaveBalance::create(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'period_year' => 2026]);
        $balance->entries()->create(['entry_type' => BalanceEntryType::Correction, 'days' => 10]);

        $summary = $this->service()->summary($balance);

        $this->assertSame('{"acquired":0.0,"carried_over":0.0,"consumed":0.0,"reserved":0.0,"adjusted":10.0,"expired":0.0,"available":10.0}', json_encode($summary, JSON_PRESERVE_ZERO_FRACTION));
    }

    private function service(): LeaveBalanceService
    {
        return app(LeaveBalanceService::class);
    }

    private function rh(): User
    {
        return $this->userWithRole(RoleName::RessourcesHumaines);
    }

    /**
     * @return array<string, float>
     */
    private function summary(Employee $employee, LeaveType $leaveType, int $year): array
    {
        return $this->service()->summary(
            LeaveBalance::where(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'period_year' => $year])->first()
        );
    }

    private function available(Employee $employee, LeaveType $leaveType, int $year): float
    {
        return $this->summary($employee, $leaveType, $year)['available'];
    }
}

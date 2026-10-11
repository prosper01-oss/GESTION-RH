<?php

namespace Tests\Feature\Services;

use App\Enums\CountingMode;
use App\Models\PublicHoliday;
use App\Services\LeaveDayCounter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeaveDayCounterTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Semaine du lundi 5 au dimanche 18 octobre 2026.
     *
     * @return array<string, array{CountingMode, string, string, bool, bool, float}>
     */
    public static function periods(): array
    {
        return [
            'calendaire, deux semaines' => [CountingMode::Calendaire, '2026-10-05', '2026-10-18', false, false, 14.0],
            'ouvré, deux semaines sans le week-end' => [CountingMode::Ouvre, '2026-10-05', '2026-10-18', false, false, 10.0],
            'ouvré, un samedi seul' => [CountingMode::Ouvre, '2026-10-10', '2026-10-10', false, false, 0.0],
            'calendaire, une journée' => [CountingMode::Calendaire, '2026-10-07', '2026-10-07', false, false, 1.0],
            'demi-journée le matin' => [CountingMode::Ouvre, '2026-10-07', '2026-10-07', false, true, 0.5],
            'début l\'après-midi et fin le matin' => [CountingMode::Ouvre, '2026-10-05', '2026-10-09', true, true, 4.0],
            'demi-journée sur un jour non ouvré ignorée' => [CountingMode::Ouvre, '2026-10-05', '2026-10-10', false, true, 5.0],
        ];
    }

    #[DataProvider('periods')]
    public function test_counts_days_according_to_the_counting_mode(CountingMode $mode, string $start, string $end, bool $startsAfternoon, bool $endsMorning, float $expected): void
    {
        $days = (new LeaveDayCounter)->count($mode, CarbonImmutable::parse($start), CarbonImmutable::parse($end), $startsAfternoon, $endsMorning);

        $this->assertSame($expected, $days);
    }

    public function test_working_days_exclude_fixed_and_recurring_public_holidays(): void
    {
        PublicHoliday::factory()->create(['date' => '2026-10-07']);
        PublicHoliday::factory()->recurring()->create(['date' => '2000-10-08']);

        $days = (new LeaveDayCounter)->count(CountingMode::Ouvre, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-09'));

        $this->assertSame(3.0, $days);
    }

    public function test_calendar_days_include_public_holidays(): void
    {
        PublicHoliday::factory()->create(['date' => '2026-10-07']);

        $days = (new LeaveDayCounter)->count(CountingMode::Calendaire, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-09'));

        $this->assertSame(5.0, $days);
    }

    public function test_non_working_weekdays_come_from_configuration(): void
    {
        config(['omit.non_working_days' => [0]]);

        $days = (new LeaveDayCounter)->count(CountingMode::Ouvre, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-11'));

        $this->assertSame(6.0, $days);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LeaveDayCounter)->count(CountingMode::Calendaire, CarbonImmutable::parse('2026-10-09'), CarbonImmutable::parse('2026-10-05'));
    }
}

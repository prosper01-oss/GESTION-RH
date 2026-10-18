<?php

namespace App\Services;

use App\Enums\CountingMode;
use App\Models\PublicHoliday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use InvalidArgumentException;

/**
 * Nombre de jours décomptés pour une période de congé, selon le mode de décompte.
 */
class LeaveDayCounter
{
    /**
     * @param  bool  $startsAfternoon  Le premier jour ne compte que pour l'après-midi.
     * @param  bool  $endsMorning  Le dernier jour ne compte que pour le matin.
     */
    public function count(
        CountingMode $mode,
        CarbonInterface $start,
        CarbonInterface $end,
        bool $startsAfternoon = false,
        bool $endsMorning = false,
    ): float {
        $start = CarbonImmutable::parse($start->toDateString());
        $end = CarbonImmutable::parse($end->toDateString());

        if ($end->lt($start)) {
            throw new InvalidArgumentException('La date de fin précède la date de début.');
        }

        if ($start->equalTo($end) && $startsAfternoon && $endsMorning) {
            throw new InvalidArgumentException("Une demi-journée ne peut pas commencer l'après-midi et finir le matin du même jour.");
        }

        $countedDays = $this->countedDays($mode, $start, $end);

        $days = (float) count($countedDays);
        if ($startsAfternoon && in_array($start->toDateString(), $countedDays, true)) {
            $days -= 0.5;
        }
        if ($endsMorning && in_array($end->toDateString(), $countedDays, true)) {
            $days -= 0.5;
        }

        return $days;
    }

    /**
     * @return list<string>
     */
    private function countedDays(CountingMode $mode, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = collect(CarbonPeriod::create($start, $end))->map(fn (CarbonInterface $day): string => $day->toDateString());

        if ($mode === CountingMode::Calendaire) {
            return $days->values()->all();
        }

        $holidays = PublicHoliday::datesBetween($start, $end)->all();
        $nonWorkingWeekdays = $mode === CountingMode::Ouvre ? config('omit.non_working_days') : [];

        return $days
            ->reject(fn (string $day): bool => in_array(CarbonImmutable::parse($day)->dayOfWeek, $nonWorkingWeekdays, true))
            ->reject(fn (string $day): bool => in_array($day, $holidays, true))
            ->values()
            ->all();
    }
}

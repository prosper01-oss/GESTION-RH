<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\PermissionRequestDetail;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Quota annuel de permissions (EF-33) : un nombre fixe de jours par année civile,
 * remis à zéro chaque 1er janvier, sans report du reliquat.
 *
 * Décompte par date, sur les heures de service (« workday ») : moins de
 * « permission_half_day_hours » heures ce jour-là compte une demi-journée, au-delà une journée. Une permission à cheval sur deux
 * années est imputée à chacune pour ses propres dates.
 */
class PermissionQuota
{
    public function annualDays(): float
    {
        return (float) config('omit.permission_annual_days');
    }

    /**
     * Jours décomptés pour une période, ventilés par année.
     *
     * @return array<int, float>
     */
    public function daysByYear(CarbonInterface $start, CarbonInterface $end): array
    {
        $start = CarbonImmutable::instance($start);
        $end = CarbonImmutable::instance($end);

        return $this->countDays($start, $end, withinWorkday: true) ?: $this->countDays($start, $end, withinWorkday: false);
    }

    /**
     * @return array<int, float>
     */
    private function countDays(CarbonImmutable $start, CarbonImmutable $end, bool $withinWorkday): array
    {
        $halfDayMinutes = (float) config('omit.permission_half_day_hours') * 60;
        $years = [];

        for ($day = $start->startOfDay(); $day->lt($end); $day = $day->addDay()) {
            [$dayStart, $dayEnd] = $withinWorkday
                ? [$day->setTimeFromTimeString(config('omit.workday.start')), $day->setTimeFromTimeString(config('omit.workday.end'))]
                : [$day, $day->addDay()];
            $from = $start->max($dayStart);
            $to = $end->min($dayEnd);

            if ($to->lte($from)) {
                continue;
            }

            $years[$day->year] = ($years[$day->year] ?? 0) + ($from->diffInMinutes($to) < $halfDayMinutes ? 0.5 : 1.0);
        }

        return $years;
    }

    public function daysInYear(CarbonInterface $start, CarbonInterface $end, int $year): float
    {
        return $this->daysByYear($start, $end)[$year] ?? 0.0;
    }

    /**
     * Jours déjà engagés sur l'année : demandes en cours d'instruction ou accordées.
     */
    public function usedDays(Employee $employee, int $year, ?int $exceptRequestId = null): float
    {
        $yearStart = CarbonImmutable::create($year)->startOfYear();

        return (float) PermissionRequestDetail::query()
            ->whereHas('request', fn ($query) => $query
                ->where('employee_id', $employee->id)
                ->whereIn('status', RequestStatus::occupyingPeriod())
                ->when($exceptRequestId !== null, fn ($q) => $q->whereKeyNot($exceptRequestId)))
            ->where('start_at', '<', $yearStart->addYear())
            ->where('end_at', '>', $yearStart)
            ->get()
            ->sum(fn (PermissionRequestDetail $detail): float => $this->daysInYear($detail->start_at, $detail->end_at, $year));
    }

    /**
     * @return array{year: int, entitlement: float|null, used: float, remaining: float|null}
     */
    public function summary(Employee $employee, int $year): array
    {
        $entitlement = $this->annualDays();
        $used = $this->usedDays($employee, $year);

        return [
            'year' => $year,
            'entitlement' => $entitlement > 0 ? $entitlement : null,
            'used' => $used,
            'remaining' => $entitlement > 0 ? max(0, $entitlement - $used) : null,
        ];
    }
}

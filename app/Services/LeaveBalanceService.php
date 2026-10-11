<?php

namespace App\Services;

use App\Enums\AccrualRule;
use App\Enums\BalanceEntryType;
use App\Enums\EmployeeStatus;
use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceEntry;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Tenue du registre des soldes de congés (sections 4.3 et 8.3 du cahier des charges).
 *
 * Solde disponible = acquis + reports − consommés − réservés (± corrections, − péremptions),
 * c'est-à-dire la somme signée de tous les mouvements de l'année.
 */
class LeaveBalanceService
{
    /**
     * Détail d'un solde par nature de mouvement.
     *
     * @return array{acquired: float, carried_over: float, consumed: float, reserved: float, adjusted: float, expired: float, available: float}
     */
    public function summary(?LeaveBalance $balance): array
    {
        $totals = $balance
            ? $balance->entries()
                ->selectRaw('entry_type, SUM(days) as total')
                ->groupBy('entry_type')
                ->pluck('total', 'entry_type')
                ->map(fn ($total): float => (float) $total)
            : collect();

        $sum = fn (BalanceEntryType ...$types): float => round(
            collect($types)->sum(fn (BalanceEntryType $type): float => $totals->get($type->value, 0.0)),
            2,
        );

        return [
            'acquired' => $sum(BalanceEntryType::Acquisition),
            'carried_over' => $sum(BalanceEntryType::Report),
            'consumed' => -$sum(BalanceEntryType::Consommation),
            'reserved' => -$sum(BalanceEntryType::Reservation, BalanceEntryType::AnnulationReservation),
            'adjusted' => $sum(BalanceEntryType::Correction),
            'expired' => -$sum(BalanceEntryType::Peremption),
            'available' => round($totals->sum(), 2),
        ];
    }

    /**
     * Soldes d'un agent pour une année, pour chaque type de congé actif tenu en solde.
     *
     * @return Collection<int, array{leave_type: LeaveType, balance: ?LeaveBalance, summary: array<string, float>}>
     */
    public function balancesFor(Employee $employee, int $year): Collection
    {
        $balances = $employee->leaveBalances()
            ->where('period_year', $year)
            ->with(['entries' => fn ($query) => $query->with('author')->orderBy('created_at')->orderBy('id')])
            ->get()
            ->keyBy('leave_type_id');

        return LeaveType::query()->active()->where('has_balance', true)->orderBy('name')->get()
            ->map(fn (LeaveType $type): array => [
                'leave_type' => $type,
                'balance' => $balances->get($type->id),
                'summary' => $this->summary($balances->get($type->id)),
            ]);
    }

    /**
     * Correction manuelle par les RH, justifiée (EF-26).
     */
    public function adjust(Employee $employee, LeaveType $leaveType, int $year, float $days, string $reason, User $author): LeaveBalanceEntry
    {
        return DB::transaction(function () use ($employee, $leaveType, $year, $days, $reason, $author): LeaveBalanceEntry {
            $balance = $this->lockedBalance($employee, $leaveType, $year);

            return $balance->entries()->create([
                'entry_type' => BalanceEntryType::Correction,
                'days' => round($days, 2),
                'reason' => $reason,
                'created_by' => $author->id,
            ]);
        });
    }

    /**
     * Solde demandable : disponible moins les jours des autres demandes en cours d'instruction (section 4.3).
     */
    public function requestableDays(Employee $employee, LeaveType $leaveType, int $year, ?EmployeeRequest $except = null): float
    {
        $balance = LeaveBalance::query()
            ->where(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'period_year' => $year])
            ->first();

        return round($this->summary($balance)['available'] - $this->pendingDays($employee, $leaveType, $year, $except), 2);
    }

    /**
     * Vérifie le solde demandable ; refuse (409) s'il est insuffisant.
     */
    public function ensureRequestable(EmployeeRequest $request): void
    {
        $detail = $request->leaveDetail;
        if (! $detail->leaveType->has_balance) {
            return;
        }

        $requestable = $this->requestableDays($request->employee, $detail->leaveType, $detail->start_date->year, $request);

        if ((float) $detail->requested_days > $requestable) {
            throw new ConflictHttpException(sprintf(
                'Solde insuffisant : %s jour(s) demandé(s), %s jour(s) disponible(s) en tenant compte des demandes en cours.',
                $this->formatDays((float) $detail->requested_days),
                $this->formatDays($requestable),
            ));
        }
    }

    /**
     * Réserve les jours d'un congé approuvé, après revérification du solde sous verrou (section 3.3).
     */
    public function reserve(EmployeeRequest $request): void
    {
        $detail = $request->leaveDetail;
        if (! $detail->leaveType->has_balance) {
            return;
        }

        DB::transaction(function () use ($request, $detail): void {
            $balance = $this->lockedBalance($request->employee, $detail->leaveType, $detail->start_date->year);
            $available = $this->summary($balance)['available'];

            if ((float) $detail->requested_days > $available) {
                throw new ConflictHttpException(sprintf(
                    'Solde devenu insuffisant : %s jour(s) demandé(s), %s jour(s) disponible(s). La demande ne peut pas être approuvée.',
                    $this->formatDays((float) $detail->requested_days),
                    $this->formatDays($available),
                ));
            }

            $balance->entries()->create([
                'request_id' => $request->id,
                'entry_type' => BalanceEntryType::Reservation,
                'days' => -(float) $detail->requested_days,
                'reason' => "Réservation {$request->reference}",
            ]);
        });
    }

    /**
     * Libère la réservation d'un congé (annulation ou clôture).
     */
    public function releaseReservation(EmployeeRequest $request, string $reason): void
    {
        $detail = $request->leaveDetail;
        if (! $detail->leaveType->has_balance) {
            return;
        }

        DB::transaction(function () use ($request, $detail, $reason): void {
            $balance = $this->lockedBalance($request->employee, $detail->leaveType, $detail->start_date->year);
            $reserved = -(float) $balance->entries()
                ->where('request_id', $request->id)
                ->whereIn('entry_type', [BalanceEntryType::Reservation, BalanceEntryType::AnnulationReservation])
                ->sum('days');

            if ($reserved > 0) {
                $balance->entries()->create([
                    'request_id' => $request->id,
                    'entry_type' => BalanceEntryType::AnnulationReservation,
                    'days' => round($reserved, 2),
                    'reason' => $reason,
                ]);
            }
        });
    }

    /**
     * Transforme la réservation en consommation définitive à la fin du congé.
     */
    public function consume(EmployeeRequest $request, float $days): void
    {
        $detail = $request->leaveDetail;
        if (! $detail->leaveType->has_balance) {
            return;
        }

        DB::transaction(function () use ($request, $detail, $days): void {
            $this->releaseReservation($request, "Fin de réservation {$request->reference}");
            $balance = $this->lockedBalance($request->employee, $detail->leaveType, $detail->start_date->year);

            $balance->entries()->create([
                'request_id' => $request->id,
                'entry_type' => BalanceEntryType::Consommation,
                'days' => -round($days, 2),
                'reason' => "Congé {$request->reference}",
            ]);
        });
    }

    private function pendingDays(Employee $employee, LeaveType $leaveType, int $year, ?EmployeeRequest $except): float
    {
        return (float) EmployeeRequest::query()
            ->join('leave_request_details', 'leave_request_details.request_id', '=', 'requests.id')
            ->where('requests.employee_id', $employee->id)
            ->whereIn('requests.status', RequestStatus::pending())
            ->where('leave_request_details.leave_type_id', $leaveType->id)
            ->whereYear('leave_request_details.start_date', $year)
            ->when($except, fn ($query) => $query->where('requests.id', '!=', $except->id))
            ->sum('leave_request_details.requested_days');
    }

    private function formatDays(float $days): string
    {
        return rtrim(rtrim(number_format($days, 2, ',', ' '), '0'), ',');
    }

    /**
     * Acquisition mensuelle pour un mois écoulé : un agent actif, embauché au plus tard le 1er du mois,
     * reçoit le droit annuel / 12. Sans effet si le mois a déjà été crédité.
     *
     * @return int Nombre d'acquisitions créées.
     */
    public function accrueMonth(CarbonImmutable $month): int
    {
        $month = $month->startOfMonth();
        $created = 0;

        foreach (LeaveType::query()->active()->where('has_balance', true)->get() as $leaveType) {
            $policy = $leaveType->policyAt($month);
            if ($policy?->accrual_rule !== AccrualRule::Mensuelle || $policy->monthlyEntitlement() <= 0) {
                continue;
            }

            $this->eligibleEmployees($month)->each(function (Employee $employee) use ($leaveType, $month, $policy, &$created): void {
                $created += (int) $this->creditOnce(
                    $employee, $leaveType, $month->year, $month->format('Y-m'), $policy->monthlyEntitlement(), 'Acquisition du mois '.$month->format('m/Y'),
                );
            });
        }

        return $created;
    }

    /**
     * Clôture d'une année : le solde restant est reporté sur l'année suivante dans la limite
     * du report autorisé, le reste est périmé. Les droits annuels de l'année suivante sont crédités.
     * Sans effet sur ce qui a déjà été traité.
     *
     * @return array{carried_over: int, expired: int, annual_credits: int}
     */
    public function closeYear(int $year): array
    {
        $result = ['carried_over' => 0, 'expired' => 0, 'annual_credits' => 0];
        $nextYear = $year + 1;

        $balances = LeaveBalance::query()
            ->where('period_year', $year)
            ->whereHas('leaveType', fn ($query) => $query->where('has_balance', true))
            ->with('leaveType', 'employee')
            ->get();

        foreach ($balances as $balance) {
            DB::transaction(function () use ($balance, $year, $nextYear, &$result): void {
                $balance = LeaveBalance::query()->lockForUpdate()->findOrFail($balance->id);
                $alreadyClosed = $balance->entries()->where('reference_period', (string) $year)
                    ->whereIn('entry_type', [BalanceEntryType::Report, BalanceEntryType::Peremption])
                    ->exists();
                $available = $this->summary($balance)['available'];

                if ($alreadyClosed || $available <= 0) {
                    return;
                }

                $policy = $balance->leaveType->policyAt(CarbonImmutable::create($year, 12, 31));
                $carried = $policy?->carry_over_max === null ? $available : min($available, (float) $policy->carry_over_max);
                $expired = round($available - $carried, 2);

                if ($carried > 0) {
                    $balance->entries()->create([
                        'entry_type' => BalanceEntryType::Report,
                        'days' => -$carried,
                        'reference_period' => (string) $year,
                        'reason' => "Report vers {$nextYear}",
                    ]);
                    $this->creditOnce($balance->employee, $balance->leaveType, $nextYear, (string) $year, $carried, "Report de {$year}", BalanceEntryType::Report);
                    $result['carried_over']++;
                }

                if ($expired > 0) {
                    $balance->entries()->create([
                        'entry_type' => BalanceEntryType::Peremption,
                        'days' => -$expired,
                        'reference_period' => (string) $year,
                        'reason' => 'Dépassement du report autorisé',
                    ]);
                    $result['expired']++;
                }
            });
        }

        $result['annual_credits'] = $this->accrueYear($nextYear);

        return $result;
    }

    /**
     * Droits annuels crédités en une fois pour les types à acquisition annuelle.
     */
    public function accrueYear(int $year): int
    {
        $firstDay = CarbonImmutable::create($year, 1, 1);
        $created = 0;

        foreach (LeaveType::query()->active()->where('has_balance', true)->get() as $leaveType) {
            $policy = $leaveType->policyAt($firstDay);
            if ($policy?->accrual_rule !== AccrualRule::Annuelle || (float) $policy->annual_entitlement <= 0) {
                continue;
            }

            $this->eligibleEmployees($firstDay)->each(function (Employee $employee) use ($leaveType, $year, $policy, &$created): void {
                $created += (int) $this->creditOnce($employee, $leaveType, $year, (string) $year, (float) $policy->annual_entitlement, "Droits annuels {$year}");
            });
        }

        return $created;
    }

    /**
     * @return Collection<int, Employee>
     */
    private function eligibleEmployees(CarbonImmutable $date): Collection
    {
        return Employee::query()
            ->where('status', EmployeeStatus::Actif)
            ->whereDate('hire_date', '<=', $date->toDateString())
            ->get();
    }

    /**
     * Crédite un mouvement s'il n'existe pas déjà pour cette période de référence.
     */
    private function creditOnce(
        Employee $employee,
        LeaveType $leaveType,
        int $year,
        string $referencePeriod,
        float $days,
        string $reason,
        BalanceEntryType $type = BalanceEntryType::Acquisition,
    ): bool {
        return DB::transaction(function () use ($employee, $leaveType, $year, $referencePeriod, $days, $reason, $type): bool {
            $balance = $this->lockedBalance($employee, $leaveType, $year);

            return $balance->entries()->firstOrCreate(
                ['entry_type' => $type, 'reference_period' => $referencePeriod],
                ['days' => round($days, 2), 'reason' => $reason],
            )->wasRecentlyCreated;
        });
    }

    /**
     * Solde verrouillé pour la durée de la transaction, créé au besoin (contre les doubles écritures).
     */
    private function lockedBalance(Employee $employee, LeaveType $leaveType, int $year): LeaveBalance
    {
        $attributes = ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'period_year' => $year];

        LeaveBalance::query()->firstOrCreate($attributes);

        return LeaveBalance::query()->where($attributes)->lockForUpdate()->firstOrFail();
    }
}

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

        // « + 0.0 » transforme le zéro négatif (-0.0) en zéro, pour ne jamais afficher « -0 j ».
        return [
            'acquired' => $sum(BalanceEntryType::Acquisition) + 0.0,
            'carried_over' => $sum(BalanceEntryType::Report) + 0.0,
            'consumed' => -$sum(BalanceEntryType::Consommation) + 0.0,
            'reserved' => -$sum(BalanceEntryType::Reservation, BalanceEntryType::AnnulationReservation) + 0.0,
            'adjusted' => $sum(BalanceEntryType::Correction) + 0.0,
            'expired' => -$sum(BalanceEntryType::Peremption) + 0.0,
            'available' => round($totals->sum(), 2) + 0.0,
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
     * Toutes les années pour lesquelles un agent a au moins une entrée de solde.
     *
     * @return list<int>
     */
    public function availableYears(Employee $employee, LeaveType $leaveType): array
    {
        return LeaveBalance::query()
            ->where(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id])
            ->pluck('period_year')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Décomposition mensuelle du solde pour une année donnée.
     * Pour chaque mois (janvier → mois en cours pour l'année actuelle, ou décembre sinon) :
     * - acquired  : jours acquis ce mois (acquisition mensuelle, référence AAAA-MM)
     * - consumed  : jours consommés ce mois (valeur absolue)
     * - available : solde cumulé disponible en fin de mois (toutes années, tous mouvements)
     *
     * @return array<int, array{month: string, label: string, acquired: float, consumed: float, available: float}>
     */
    public function monthlyBreakdown(Employee $employee, LeaveType $leaveType, int $year): array
    {
        $balanceIds = LeaveBalance::query()
            ->where(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id])
            ->pluck('id');

        if ($balanceIds->isEmpty()) {
            return [];
        }

        $currentYear = (int) now()->year;
        $lastMonth = $year < $currentYear ? 12 : now()->month;

        $result = [];

        for ($m = 1; $m <= $lastMonth; $m++) {
            $period = sprintf('%04d-%02d', $year, $m);
            $endOfMonth = CarbonImmutable::create($year, $m, 1)->endOfMonth();

            // Acquisition mensuelle créditée pour ce mois précis
            $acquired = (float) LeaveBalanceEntry::query()
                ->whereIn('leave_balance_id', $balanceIds)
                ->where('entry_type', BalanceEntryType::Acquisition)
                ->where('reference_period', $period)
                ->sum('days');

            // Consommations enregistrées pendant ce mois calendaire
            $consumed = abs((float) LeaveBalanceEntry::query()
                ->whereIn('leave_balance_id', $balanceIds)
                ->where('entry_type', BalanceEntryType::Consommation)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $m)
                ->sum('days'));

            // Solde total disponible en fin de mois (tous mouvements confondus jusqu'à cette date)
            $available = round((float) LeaveBalanceEntry::query()
                ->whereIn('leave_balance_id', $balanceIds)
                ->where('created_at', '<=', $endOfMonth->toDateTimeString())
                ->sum('days'), 2) + 0.0;

            $result[] = [
                'month' => $period,
                'label' => CarbonImmutable::create($year, $m, 1)->locale('fr')->isoFormat('MMMM'),
                'acquired' => round($acquired, 2) + 0.0,
                'consumed' => round($consumed, 2) + 0.0,
                'available' => $available,
            ];
        }

        return $result;
    }

    /**
     * Total des jours disponibles pour un agent sur tous les exercices (toutes années confondues).
     */
    public function totalAvailableAllYears(Employee $employee, LeaveType $leaveType): float
    {
        return (float) LeaveBalance::query()
            ->where(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id])
            ->join('leave_balance_entries', 'leave_balance_entries.leave_balance_id', '=', 'leave_balances.id')
            ->sum('leave_balance_entries.days');
    }

    /**
     * Soldes restants par année pour un agent (toutes les années ayant un solde).
     *
     * @return array<int, array{year: int, available: float}>
     */
    public function balancesByYear(Employee $employee, LeaveType $leaveType): array
    {
        $balances = LeaveBalance::query()
            ->where(['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id])
            ->orderBy('period_year')
            ->get();

        return $balances->map(fn (LeaveBalance $balance): array => [
            'year' => $balance->period_year,
            'available' => $this->summary($balance)['available'],
        ])->values()->all();
    }

    /**
     * Acquisition mensuelle pour un mois écoulé : un agent actif, embauché au plus tard le 1er du mois,
     * reçoit le droit annuel / 12. Sans effet si le mois a déjà été crédité.
     * Le crédit est plafonné si le total cumulé atteint OMIT_MAX_BALANCE_DAYS.
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
                $entitlement = $this->cappedEntitlement($employee, $leaveType, $policy->monthlyEntitlement());
                if ($entitlement <= 0) {
                    return;
                }

                $created += (int) $this->creditOnce(
                    $employee, $leaveType, $month->year, $month->format('Y-m'), $entitlement, 'Acquisition du mois '.$month->format('m/Y'),
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
     * Le crédit est plafonné si le total cumulé atteint OMIT_MAX_BALANCE_DAYS.
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
                $entitlement = $this->cappedEntitlement($employee, $leaveType, (float) $policy->annual_entitlement);
                if ($entitlement <= 0) {
                    return;
                }

                $created += (int) $this->creditOnce($employee, $leaveType, $year, (string) $year, $entitlement, "Droits annuels {$year}");
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
     * Réduit le droit à créditer pour ne pas dépasser OMIT_MAX_BALANCE_DAYS (toutes années confondues).
     * Retourne 0 si le plafond est déjà atteint ou si le paramètre est désactivé (0).
     */
    private function cappedEntitlement(Employee $employee, LeaveType $leaveType, float $entitlement): float
    {
        $max = (int) config('omit.max_balance_days', 0);
        if ($max <= 0) {
            return $entitlement;
        }

        $current = $this->totalAvailableAllYears($employee, $leaveType);
        $room = round($max - $current, 2);

        return max(0.0, min($entitlement, $room));
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

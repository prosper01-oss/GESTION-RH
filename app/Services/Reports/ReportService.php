<?php

namespace App\Services\Reports;

use App\Enums\EmployeeStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Indicateurs et rapports RH (EF-70 à EF-74).
 * Une absence est une demande approuvée, finalisée ou terminée qui recoupe la période.
 */
class ReportService
{
    /**
     * @var list<RequestStatus>
     */
    private const GRANTED = [RequestStatus::Approuvee, RequestStatus::Finalisee, RequestStatus::Terminee];

    /**
     * Tableau de bord RH (EF-70, EF-71).
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();

        $byTypeAndStatus = EmployeeRequest::query()
            ->where('status', '!=', RequestStatus::Brouillon)
            ->selectRaw('request_type, status, COUNT(*) as total')
            ->groupBy('request_type', 'status')
            ->get()
            ->map(fn (EmployeeRequest $row): array => [
                'request_type' => $row->request_type->value,
                'status' => $row->status->value,
                'status_label' => $row->status->label(),
                'total' => (int) $row->total,
            ])
            ->values();

        return [
            'active_employees' => Employee::query()->where('status', EmployeeStatus::Actif)->count(),
            'employees_by_department' => Department::query()
                ->withCount(['employees' => fn ($query) => $query->where('status', EmployeeStatus::Actif)])
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department): array => ['name' => $department->name, 'count' => $department->employees_count])
                ->values(),
            'pending' => [
                'control' => EmployeeRequest::query()->where('request_type', '!=', RequestType::Sortie)->whereIn('status', [RequestStatus::Soumise, RequestStatus::RenvoyeeRh])->count(),
                'decide' => EmployeeRequest::query()->where('status', RequestStatus::TransmiseMedecin)->count(),
                'finalize' => EmployeeRequest::query()->where('request_type', '!=', RequestType::Sortie)->where('status', RequestStatus::Approuvee)->count(),
                'exits' => EmployeeRequest::query()->where('request_type', RequestType::Sortie)->where('status', RequestStatus::Soumise)->count(),
            ],
            'leaves_granted_this_month' => $this->absenceQuery($monthStart, $monthEnd)->where('request_type', RequestType::Conge)->count(),
            'absent_today' => $this->absenceQuery($today, $today)->whereIn('request_type', [RequestType::Conge, RequestType::Permission])->distinct()->count('employee_id'),
            'exits_today' => $this->absenceQuery($today, $today)->where('request_type', RequestType::Sortie)->count(),
            'exits_in_progress' => EmployeeRequest::query()
                ->whereIn('status', [RequestStatus::Approuvee, RequestStatus::Finalisee])
                ->whereHas('exitDetail', fn ($query) => $query->whereNotNull('actual_departure_at')->whereNull('actual_return_at'))
                ->count(),
            'requests_by_type_and_status' => $byTypeAndStatus,
        ];
    }

    /**
     * Absences d'une période (EF-72), filtrables par service et par type.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function absences(CarbonImmutable $from, CarbonImmutable $to, ?int $departmentId = null, ?RequestType $type = null): Collection
    {
        return $this->absenceQuery($from, $to)
            ->with(['employee.department', 'leaveDetail.leaveType', 'permissionDetail.permissionType', 'exitDetail'])
            ->when($departmentId, fn (Builder $query) => $query->whereHas('employee', fn (Builder $query) => $query->where('department_id', $departmentId)))
            ->when($type, fn (Builder $query) => $query->where('request_type', $type))
            ->get()
            ->map(fn (EmployeeRequest $request): array => $this->row($request))
            ->sortBy([['start', 'asc'], ['employee', 'asc']])
            ->values();
    }

    /**
     * Totaux d'un rapport d'absences, par type.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, array{count: int, days: float, hours: float}>
     */
    public function totals(Collection $rows): array
    {
        return collect(RequestType::cases())->mapWithKeys(fn (RequestType $type): array => [$type->value => [
            'count' => $rows->where('request_type', $type->value)->count(),
            'days' => round($rows->where('request_type', $type->value)->sum('days'), 2),
            'hours' => round($rows->where('request_type', $type->value)->sum('hours'), 2),
        ]])->all();
    }

    /**
     * Demandes accordées qui recoupent la période [from, to].
     *
     * @return Builder<EmployeeRequest>
     */
    private function absenceQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $start = $from->startOfDay();
        $end = $to->endOfDay();

        return EmployeeRequest::query()
            ->whereIn('status', self::GRANTED)
            ->where(fn (Builder $query) => $query
                ->whereHas('leaveDetail', fn (Builder $query) => $query
                    ->whereDate('start_date', '<=', $end->toDateString())
                    ->whereDate('end_date', '>=', $start->toDateString()))
                ->orWhereHas('permissionDetail', fn (Builder $query) => $query
                    ->where('start_at', '<=', $end)
                    ->where('end_at', '>=', $start))
                ->orWhereHas('exitDetail', fn (Builder $query) => $query
                    ->where('departure_at', '<=', $end)
                    ->where('expected_return_at', '>=', $start)));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(EmployeeRequest $request): array
    {
        $employee = $request->employee;
        $row = [
            'id' => $request->id,
            'reference' => $request->reference,
            'request_type' => $request->request_type->value,
            'type_label' => $request->request_type->label(),
            'matricule' => $employee->matricule,
            'employee' => "{$employee->nom} {$employee->prenom}",
            'department' => $employee->department?->name ?? '—',
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'days' => 0.0,
            'hours' => 0.0,
        ];

        if ($detail = $request->leaveDetail) {
            return array_merge($row, [
                'nature' => $detail->leaveType->name,
                'start' => $detail->start_date->format('Y-m-d'),
                'end' => ($detail->actual_end_date ?? $detail->end_date)->format('Y-m-d'),
                'days' => (float) ($detail->consumed_days ?? $detail->requested_days),
            ]);
        }

        if ($detail = $request->permissionDetail) {
            return array_merge($row, [
                'nature' => $detail->permissionType->name,
                'start' => $detail->start_at->format('Y-m-d H:i'),
                'end' => $detail->end_at->format('Y-m-d H:i'),
                'hours' => (float) $detail->duration_hours,
            ]);
        }

        $detail = $request->exitDetail;
        $returnAt = $detail->actual_return_at ?? $detail->expected_return_at;
        $departureAt = $detail->actual_departure_at ?? $detail->departure_at;

        return array_merge($row, [
            'nature' => "Sortie : {$detail->destination}",
            'start' => $departureAt->format('Y-m-d H:i'),
            'end' => $returnAt->format('Y-m-d H:i'),
            'hours' => round($departureAt->diffInMinutes($returnAt) / 60, 2),
        ]);
    }
}

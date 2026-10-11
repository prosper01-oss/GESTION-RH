<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Enums\PermissionName;
use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tableau de bord et rapports RH (EF-70 à EF-74), réservés aux personnes habilitées.
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports, private ReportExporter $exporter) {}

    public function dashboard(): JsonResponse
    {
        Gate::authorize(PermissionName::ViewReports->value);

        return response()->json(['data' => $this->reports->dashboard()]);
    }

    public function absences(Request $request): JsonResponse
    {
        Gate::authorize(PermissionName::ViewReports->value);
        [$from, $to, $departmentId, $type] = $this->filters($request);

        $rows = $this->reports->absences($from, $to, $departmentId, $type);

        return response()->json(['data' => $rows, 'totals' => $this->reports->totals($rows)]);
    }

    /**
     * Rapport des absences en PDF ou Excel.
     */
    public function exportAbsences(Request $request): Response
    {
        Gate::authorize(PermissionName::ViewReports->value);
        [$from, $to, $departmentId, $type] = $this->filters($request);
        $format = $request->validate(['format' => ['required', Rule::in(['pdf', 'xlsx'])]])['format'];

        $rows = $this->reports->absences($from, $to, $departmentId, $type);
        $totals = $this->reports->totals($rows);
        $summary = [
            'Période du '.$from->format('d/m/Y').' au '.$to->format('d/m/Y')
                .($departmentId ? ' · Service : '.Department::find($departmentId)?->name : '')
                .($type ? ' · Type : '.$type->label() : ''),
            sprintf('Congés : %d (%s jours) · Permissions : %d (%s h) · Sorties : %d',
                $totals['conge']['count'], $this->number($totals['conge']['days']),
                $totals['permission']['count'], $this->number($totals['permission']['hours']),
                $totals['sortie']['count']),
        ];
        $headers = ['Référence', 'Matricule', 'Agent', 'Service', 'Type', 'Nature', 'Début', 'Fin', 'Jours', 'Heures', 'Statut'];
        $data = $rows->map(fn (array $row): array => [
            $row['reference'], $row['matricule'], $row['employee'], $row['department'], $row['type_label'], $row['nature'],
            $row['start'], $row['end'], $row['days'] ?: null, $row['hours'] ?: null, $row['status_label'],
        ])->all();

        AuditLog::record('report.exported', newValues: ['report' => 'absences', 'format' => $format, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
        $filename = 'absences-'.$from->format('Ymd').'-'.$to->format('Ymd');

        return $format === 'pdf'
            ? $this->exporter->pdf('Rapport des absences', $headers, $data, $summary, $filename)
            : $this->exporter->xlsx('Rapport des absences', $headers, $data, $summary, $filename);
    }

    /**
     * Effectif actif par service (EF-71), en PDF ou Excel.
     */
    public function exportStaff(Request $request): Response
    {
        Gate::authorize(PermissionName::ViewReports->value);
        $format = $request->validate(['format' => ['required', Rule::in(['pdf', 'xlsx'])]])['format'];

        $employees = Employee::query()
            ->where('status', EmployeeStatus::Actif)
            ->with('department')
            ->get()
            ->sortBy([fn ($a, $b) => strcmp($a->department?->name ?? '', $b->department?->name ?? ''), fn ($a, $b) => strcmp($a->nom, $b->nom)])
            ->values();
        $headers = ['Service', 'Matricule', 'Nom', 'Prénoms', 'Fonction', 'Contrat', "Date d'embauche"];
        $data = $employees->map(fn (Employee $employee): array => [
            $employee->department?->name, $employee->matricule, $employee->nom, $employee->prenom, $employee->position,
            mb_strtoupper($employee->contract_type->value), $employee->hire_date->format('d/m/Y'),
        ])->all();
        $byDepartment = $employees
            ->countBy(fn (Employee $employee): string => $employee->department?->name ?? '—')
            ->map(fn (int $count, string $name): string => "{$name} : {$count}")
            ->implode(' · ');
        $summary = ['Effectif actif au '.now()->format('d/m/Y').' : '.$employees->count().' agent(s)', $byDepartment];

        AuditLog::record('report.exported', newValues: ['report' => 'staff', 'format' => $format]);
        $filename = 'effectif-'.now()->format('Ymd');

        return $format === 'pdf'
            ? $this->exporter->pdf('Effectif par service', $headers, $data, $summary, $filename)
            : $this->exporter->xlsx('Effectif par service', $headers, $data, $summary, $filename);
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable, ?int, ?RequestType}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'type' => ['nullable', Rule::enum(RequestType::class)],
        ]);

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);
        abort_if($from->diffInDays($to) > 366, 422, 'La période ne peut pas dépasser un an.');

        return [$from, $to, isset($validated['department_id']) ? (int) $validated['department_id'] : null, isset($validated['type']) ? RequestType::from($validated['type']) : null];
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',') ?: '0';
    }
}

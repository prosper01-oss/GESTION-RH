<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveBalanceEntryResource;
use App\Http\Resources\LeaveTypeResource;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LeaveBalanceController extends Controller
{
    public function __construct(private LeaveBalanceService $balances) {}

    /**
     * Soldes de l'agent connecté.
     */
    public function mine(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 404, "Ce compte n'est rattaché à aucune fiche du personnel.");

        return $this->respond($employee, $this->year($request));
    }

    /**
     * Soldes d'un agent, pour les personnes habilitées.
     */
    public function show(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize(PermissionName::ViewLeaveBalances->value);

        return $this->respond($employee, $this->year($request));
    }

    /**
     * Récapitulatif multi-années : solde disponible par année et par type de congé,
     * plus le total cumulé et le plafond configuré.
     */
    public function yearsSummary(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize(PermissionName::ViewLeaveBalances->value);

        return $this->respondYears($employee);
    }

    /**
     * Récapitulatif multi-années pour l'agent connecté.
     */
    public function myYearsSummary(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 404, "Ce compte n'est rattaché à aucune fiche du personnel.");

        return $this->respondYears($employee);
    }

    /**
     * Décomposition mensuelle multi-années pour un agent (toutes les années avec un solde).
     */
    public function monthly(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize(PermissionName::ViewLeaveBalances->value);

        return $this->respondMonthly($employee);
    }

    /**
     * Décomposition mensuelle multi-années pour l'agent connecté.
     */
    public function myMonthly(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 404, "Ce compte n'est rattaché à aucune fiche du personnel.");

        return $this->respondMonthly($employee);
    }

    private function year(Request $request): int
    {
        return (int) ($request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? now()->year);
    }

    private function respond(Employee $employee, int $year): JsonResponse
    {
        return response()->json([
            'data' => $this->balances->balancesFor($employee, $year)->map(fn (array $item): array => [
                'leave_type' => LeaveTypeResource::make($item['leave_type']),
                'year' => $year,
                'summary' => $item['summary'],
                'entries' => LeaveBalanceEntryResource::collection($item['balance']?->entries ?? collect()),
            ]),
        ]);
    }

    private function respondMonthly(Employee $employee): JsonResponse
    {
        $leaveTypes = LeaveType::query()->active()->where('has_balance', true)->orderBy('name')->get();
        $currentYear = (int) now()->year;

        $data = $leaveTypes->map(function (LeaveType $type) use ($employee, $currentYear): array {
            $years = $this->balances->availableYears($employee, $type);

            // Toujours inclure l'année en cours même si aucun solde n'existe encore
            if (! in_array($currentYear, $years, true)) {
                $years[] = $currentYear;
                sort($years);
            }

            $yearData = array_map(fn (int $y): array => [
                'year' => $y,
                'months' => $this->balances->monthlyBreakdown($employee, $type, $y),
            ], $years);

            return [
                'leave_type' => LeaveTypeResource::make($type),
                'years' => $yearData,
            ];
        });

        return response()->json(['data' => $data]);
    }

    private function respondYears(Employee $employee): JsonResponse
    {
        $leaveTypes = LeaveType::query()->active()->where('has_balance', true)->orderBy('name')->get();
        $maxDays = (int) config('omit.max_balance_days', 0);

        $data = $leaveTypes->map(fn (LeaveType $type): array => [
            'leave_type' => LeaveTypeResource::make($type),
            'years' => $this->balances->balancesByYear($employee, $type),
            'total_available' => round($this->balances->totalAvailableAllYears($employee, $type), 2),
            'max_days' => $maxDays > 0 ? $maxDays : null,
        ]);

        return response()->json(['data' => $data]);
    }
}

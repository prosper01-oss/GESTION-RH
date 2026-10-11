<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveBalanceEntryResource;
use App\Http\Resources\LeaveTypeResource;
use App\Models\Employee;
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
}

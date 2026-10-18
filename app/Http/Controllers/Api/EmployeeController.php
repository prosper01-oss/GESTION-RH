<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Annuaire paginé, filtrable par nom ou matricule, service et statut (EF-14).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Employee::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],
        ]);

        $employees = Employee::query()
            ->with('department')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->search($search))
            ->when($filters['department_id'] ?? null, fn ($query, int $departmentId) => $query->where('department_id', $departmentId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate(20)
            ->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request, LeaveBalanceService $balances): JsonResponse
    {
        $employee = Employee::create($request->validated());

        AuditLog::record('employee.created', $employee, newValues: $request->validated());

        // Les congés déjà acquis cette année s'affichent dès la création de la fiche.
        $credited = $balances->accrueCurrentYearFor($employee->fresh());

        return EmployeeResource::make($employee->load('department'))
            ->additional(['leave_months_credited' => $credited])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        Gate::authorize('view', $employee);

        return EmployeeResource::make($employee->load('department'));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee->fill($request->validated());
        $changes = $employee->getDirty();
        $original = array_intersect_key($employee->getRawOriginal(), $changes);
        $employee->save();

        if ($changes !== []) {
            AuditLog::record('employee.updated', $employee, $original, $changes);
        }

        return EmployeeResource::make($employee->load('department'));
    }
}

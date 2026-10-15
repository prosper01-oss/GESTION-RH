<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveLeaveRequestRequest;
use App\Http\Resources\EmployeeRequestResource;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LeaveRequestController extends Controller
{
    public function __construct(private LeaveRequestService $leaveRequests) {}

    public function store(SaveLeaveRequestRequest $request): JsonResponse
    {
        Gate::authorize('create', EmployeeRequest::class);

        $validated = $request->validated();
        $employeeId = $validated['employee_id'] ?? null;

        if ($employeeId !== null) {
            Gate::authorize(PermissionName::ReviewRequests->value);
            $employee = Employee::findOrFail($employeeId);
        } else {
            $employee = $request->user()->employee;
            abort_if($employee === null, 422, "Ce compte n'est rattaché à aucune fiche du personnel.");
        }

        $leaveRequest = $this->leaveRequests->createDraft($employee, $validated);

        return EmployeeRequestResource::forDisplay($leaveRequest)->response()->setStatusCode(201);
    }

    public function show(EmployeeRequest $leaveRequest): EmployeeRequestResource
    {
        Gate::authorize('view', $leaveRequest);

        return EmployeeRequestResource::forDisplay($leaveRequest);
    }

    public function update(SaveLeaveRequestRequest $request, EmployeeRequest $leaveRequest): EmployeeRequestResource
    {
        Gate::authorize('update', $leaveRequest);

        return EmployeeRequestResource::forDisplay($this->leaveRequests->updateDraft($leaveRequest, $request->validated()));
    }
}

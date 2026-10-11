<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveLeaveRequestRequest;
use App\Http\Resources\EmployeeRequestResource;
use App\Models\EmployeeRequest;
use App\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    public function __construct(private LeaveRequestService $leaveRequests) {}

    /**
     * Mes demandes, ou toutes les demandes (scope=all) pour les RH et le Médecin-Chef.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['mine', 'all'])],
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'employee_id' => ['nullable', 'integer'],
        ]);
        $all = ($filters['scope'] ?? 'mine') === 'all';

        if ($all) {
            Gate::authorize('viewAny', EmployeeRequest::class);
        }

        $requests = EmployeeRequest::query()
            ->where('request_type', RequestType::Conge)
            ->with(['employee.department', 'leaveDetail.leaveType'])
            ->when(! $all, fn ($query) => $query->where('employee_id', $request->user()->employee_id ?? 0))
            ->when($all, fn ($query) => $query->where('status', '!=', RequestStatus::Brouillon))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($all && ($filters['employee_id'] ?? null), fn ($query) => $query->where('employee_id', $filters['employee_id']))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return EmployeeRequestResource::collection($requests);
    }

    public function store(SaveLeaveRequestRequest $request): JsonResponse
    {
        Gate::authorize('create', EmployeeRequest::class);

        $leaveRequest = $this->leaveRequests->createDraft($request->user()->employee, $request->validated());

        return $this->resource($leaveRequest)->response()->setStatusCode(201);
    }

    public function show(EmployeeRequest $leaveRequest): EmployeeRequestResource
    {
        Gate::authorize('view', $leaveRequest);

        return $this->resource($leaveRequest);
    }

    public function update(SaveLeaveRequestRequest $request, EmployeeRequest $leaveRequest): EmployeeRequestResource
    {
        Gate::authorize('update', $leaveRequest);

        return $this->resource($this->leaveRequests->updateDraft($leaveRequest, $request->validated()));
    }

    public function destroy(EmployeeRequest $leaveRequest): JsonResponse
    {
        Gate::authorize('delete', $leaveRequest);

        $leaveRequest->delete();

        return response()->json(['message' => 'Brouillon supprimé.']);
    }

    private function resource(EmployeeRequest $leaveRequest): EmployeeRequestResource
    {
        return EmployeeRequestResource::make($leaveRequest->fresh()->load([
            'employee.department', 'leaveDetail.leaveType', 'attachments', 'history.actor',
        ]));
    }
}

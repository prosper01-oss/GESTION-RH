<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionName;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeRequestResource;
use App\Models\EmployeeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ApprovalController extends Controller
{
    /**
     * File de l'utilisateur connecté, les plus anciennes d'abord (EF-50).
     * RH : « control » (à contrôler) et « finalize » (à finaliser) ; Médecin-Chef : « decide ».
     * Les demandes de l'utilisateur lui-même n'y figurent jamais.
     */
    public function pending(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', EmployeeRequest::class);

        $user = $request->user();
        $queue = $request->validate(['queue' => ['nullable', Rule::in(['control', 'decide', 'finalize'])]])['queue']
            ?? ($user->hasPermission(PermissionName::ReviewRequests) ? 'control' : 'decide');

        [$permission, $statuses] = match ($queue) {
            'control' => [PermissionName::ReviewRequests, [RequestStatus::Soumise, RequestStatus::RenvoyeeRh]],
            'finalize' => [PermissionName::ReviewRequests, [RequestStatus::Approuvee]],
            'decide' => [PermissionName::DecideRequests, [RequestStatus::TransmiseMedecin]],
        };
        Gate::authorize($permission->value);

        $requests = EmployeeRequest::query()
            ->whereIn('status', $statuses)
            ->when($user->employee_id, fn ($query, int $employeeId) => $query->where('employee_id', '!=', $employeeId))
            ->with(['employee.department', 'leaveDetail.leaveType'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return EmployeeRequestResource::collection($requests);
    }
}

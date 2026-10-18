<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionName;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeRequestResource;
use App\Models\EmployeeRequest;
use App\Services\ExitPassApprover;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ApprovalController extends Controller
{
    /**
     * File de l'utilisateur connecté, les plus anciennes d'abord (EF-50).
     * RH : « control » (à contrôler) et « finalize » (à finaliser) ; Médecin-Chef : « decide » ;
     * chef direct ou Médecin-Chef : « exits » (billets de sortie à valider).
     * Les demandes de l'utilisateur lui-même n'y figurent jamais.
     */
    public function pending(Request $request, ExitPassApprover $exitPassApprover): AnonymousResourceCollection
    {
        $user = $request->user();
        $queue = $request->validate(['queue' => ['nullable', Rule::in(['control', 'decide', 'finalize', 'exits'])]])['queue']
            ?? match (true) {
                $user->hasPermission(PermissionName::ReviewRequests) => 'control',
                $user->hasPermission(PermissionName::DecideRequests) => 'decide',
                default => 'exits',
            };

        // Billets de sortie (R7) : file du chef direct, ou du Médecin-Chef pour les services sans responsable.
        if ($queue === 'exits') {
            abort_unless($exitPassApprover->approvesExits($user), 403, 'Vous ne validez pas de billets de sortie.');
            $ids = $exitPassApprover->pendingIdsFor($user);

            return EmployeeRequestResource::collection(
                EmployeeRequest::query()
                    ->whereKey($ids)
                    ->with(['employee.department', ...EmployeeRequest::detailRelations()])
                    ->orderBy('submitted_at')
                    ->orderBy('id')
                    ->paginate(20)
                    ->withQueryString()
            );
        }

        [$permission, $statuses] = match ($queue) {
            'control' => [PermissionName::ReviewRequests, [RequestStatus::Soumise, RequestStatus::RenvoyeeRh]],
            'finalize' => [PermissionName::ReviewRequests, [RequestStatus::Approuvee]],
            'decide' => [PermissionName::DecideRequests, [RequestStatus::TransmiseMedecin]],
        };
        Gate::authorize($permission->value);

        $requests = EmployeeRequest::query()
            ->whereIn('status', $statuses)
            ->where('request_type', '!=', RequestType::Sortie)
            ->when($user->employee_id, fn ($query, int $employeeId) => $query->where('employee_id', '!=', $employeeId))
            ->with(['employee.department', ...EmployeeRequest::detailRelations()])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return EmployeeRequestResource::collection($requests);
    }
}

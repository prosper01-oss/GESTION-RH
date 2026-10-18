<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeRequestResource;
use App\Models\EmployeeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Liste et détail des demandes, tous types confondus.
 */
class RequestController extends Controller
{
    /**
     * Mes demandes, ou toutes (scope=all) pour les RH et le Médecin-Chef.
     * Le type peut être imposé par la route (/leave-requests, /permission-requests, /exit-passes).
     * state=en_cours ou en_retard suit les sorties en cours (EF-42).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['mine', 'all'])],
            'type' => ['nullable', Rule::enum(RequestType::class)],
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'employee_id' => ['nullable', 'integer'],
            'state' => ['nullable', Rule::in(['en_cours', 'en_retard'])],
        ]);
        $type = $request->route('type') ?? ($filters['type'] ?? null);
        $all = ($filters['scope'] ?? 'mine') === 'all';

        if ($all) {
            Gate::authorize('viewAny', EmployeeRequest::class);
        }

        $requests = EmployeeRequest::query()
            ->with(['employee.department', ...EmployeeRequest::detailRelations()])
            ->when($type, fn ($query, string $type) => $query->where('request_type', $type))
            ->when(! $all, fn ($query) => $query->where('employee_id', $request->user()->employee_id ?? 0))
            ->when($all, fn ($query) => $query->where('status', '!=', RequestStatus::Brouillon))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($all && ($filters['employee_id'] ?? null), fn ($query) => $query->where('employee_id', $filters['employee_id']))
            ->when($filters['state'] ?? null, fn ($query, string $state) => $query
                ->whereIn('status', [RequestStatus::Approuvee, RequestStatus::Finalisee])
                ->whereHas('exitDetail', fn ($query) => $query
                    ->whereNotNull('actual_departure_at')
                    ->whereNull('actual_return_at')
                    ->when($state === 'en_retard', fn ($query) => $query->where('expected_return_at', '<', now()))))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return EmployeeRequestResource::collection($requests);
    }

    public function show(EmployeeRequest $employeeRequest): EmployeeRequestResource
    {
        Gate::authorize('view', $employeeRequest);

        return EmployeeRequestResource::forDisplay($employeeRequest);
    }

    /**
     * Supprime un brouillon (les demandes envoyées ne sont jamais supprimées).
     */
    public function destroy(EmployeeRequest $employeeRequest): JsonResponse
    {
        Gate::authorize('delete', $employeeRequest);

        $employeeRequest->delete();

        return response()->json(['message' => 'Brouillon supprimé.']);
    }
}

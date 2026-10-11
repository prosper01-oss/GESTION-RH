<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveExitPassRequest;
use App\Http\Resources\EmployeeRequestResource;
use App\Models\EmployeeRequest;
use App\Services\ExitPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExitPassController extends Controller
{
    public function __construct(private ExitPassService $exitPasses) {}

    public function store(SaveExitPassRequest $request): JsonResponse
    {
        Gate::authorize('create', EmployeeRequest::class);

        $exitPass = $this->exitPasses->createDraft($request->user()->employee, $request->validated());

        return EmployeeRequestResource::forDisplay($exitPass)->response()->setStatusCode(201);
    }

    public function update(SaveExitPassRequest $request, EmployeeRequest $employeeRequest): JsonResponse
    {
        $this->ensureType($employeeRequest);
        Gate::authorize('update', $employeeRequest);

        $this->exitPasses->updateDraft($employeeRequest, $request->validated());

        return EmployeeRequestResource::forDisplay($employeeRequest)->response();
    }

    /**
     * Départ effectif, enregistré par les RH.
     */
    public function recordDeparture(Request $request, EmployeeRequest $employeeRequest): JsonResponse
    {
        $this->ensureType($employeeRequest);
        Gate::authorize('process', $employeeRequest);

        $validated = $request->validate(['actual_departure_at' => ['nullable', 'date', 'before_or_equal:now']]);
        $this->exitPasses->recordDeparture($employeeRequest, $validated['actual_departure_at'] ?? null);

        return EmployeeRequestResource::forDisplay($employeeRequest)->response();
    }

    /**
     * Retour effectif : le billet est clôturé.
     */
    public function recordReturn(Request $request, EmployeeRequest $employeeRequest): JsonResponse
    {
        $this->ensureType($employeeRequest);
        Gate::authorize('process', $employeeRequest);

        $validated = $request->validate(['actual_return_at' => ['nullable', 'date', 'before_or_equal:now']]);
        $this->exitPasses->recordReturn($employeeRequest, $validated['actual_return_at'] ?? null, $request->user());

        return EmployeeRequestResource::forDisplay($employeeRequest)->response();
    }

    private function ensureType(EmployeeRequest $employeeRequest): void
    {
        if ($employeeRequest->request_type !== RequestType::Sortie) {
            throw new NotFoundHttpException;
        }
    }
}

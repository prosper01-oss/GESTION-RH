<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SavePermissionRequestRequest;
use App\Http\Resources\EmployeeRequestResource;
use App\Models\EmployeeRequest;
use App\Services\PermissionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PermissionRequestController extends Controller
{
    public function __construct(private PermissionRequestService $permissions) {}

    public function store(SavePermissionRequestRequest $request): JsonResponse
    {
        Gate::authorize('create', EmployeeRequest::class);

        $permission = $this->permissions->createDraft($request->user()->employee, $request->validated());

        return EmployeeRequestResource::forDisplay($permission)->response()->setStatusCode(201);
    }

    public function update(SavePermissionRequestRequest $request, EmployeeRequest $employeeRequest): JsonResponse
    {
        $this->ensureType($employeeRequest);
        Gate::authorize('update', $employeeRequest);

        $this->permissions->updateDraft($employeeRequest, $request->validated());

        return EmployeeRequestResource::forDisplay($employeeRequest)->response();
    }

    private function ensureType(EmployeeRequest $employeeRequest): void
    {
        if ($employeeRequest->request_type !== RequestType::Permission) {
            throw new NotFoundHttpException;
        }
    }
}

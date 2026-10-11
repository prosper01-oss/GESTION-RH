<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Department::class);

        return DepartmentResource::collection(
            Department::query()->with('manager')->withCount('employees')->orderBy('name')->get()
        );
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        AuditLog::record('department.created', $department, newValues: $request->validated());

        return DepartmentResource::make($department->load('manager'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Department $department): DepartmentResource
    {
        Gate::authorize('view', $department);

        return DepartmentResource::make($department->load('manager')->loadCount('employees'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $department->fill($request->validated());
        $changes = $department->getDirty();
        $original = array_intersect_key($department->getRawOriginal(), $changes);
        $department->save();

        if ($changes !== []) {
            AuditLog::record('department.updated', $department, $original, $changes);
        }

        return DepartmentResource::make($department->load('manager'));
    }
}

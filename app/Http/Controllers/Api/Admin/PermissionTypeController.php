<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PermissionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Catégories de permission (EF-30).
 */
class PermissionTypeController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize(PermissionName::ManageLeaveSettings->value);

        return response()->json(['data' => PermissionType::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize(PermissionName::ManageLeaveSettings->value);

        $type = PermissionType::create($this->validated($request));
        AuditLog::record('permission_type.created', $type, newValues: $type->toArray());

        return response()->json(['data' => $type], 201);
    }

    public function update(Request $request, PermissionType $permissionType): JsonResponse
    {
        Gate::authorize(PermissionName::ManageLeaveSettings->value);

        $old = $permissionType->only(['name', 'is_paid', 'requires_attachment', 'is_medical', 'max_duration_hours', 'is_active']);
        $permissionType->update($this->validated($request, $permissionType));
        AuditLog::record('permission_type.updated', $permissionType, $old, $permissionType->only(array_keys($old)));

        return response()->json(['data' => $permissionType]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PermissionType $type = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permission_types', 'name')->ignore($type)],
            'is_paid' => ['required', 'boolean'],
            'requires_attachment' => ['required', 'boolean'],
            'is_medical' => ['required', 'boolean'],
            'max_duration_hours' => ['nullable', 'numeric', 'min:0.5', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}

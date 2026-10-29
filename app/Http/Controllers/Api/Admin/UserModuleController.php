<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserModuleController extends Controller
{
    /**
     * Remplace les modules Pharmacie accordés individuellement à un compte, en plus de ceux de ses rôles.
     */
    public function update(Request $request, User $user): UserResource
    {
        Gate::authorize(PermissionName::ManageUsers->value);

        $validated = $request->validate([
            'modules' => ['present', 'array'],
            'modules.*' => ['distinct', Rule::in(array_map(fn (PermissionName $permission): string => $permission->value, PermissionName::pharma()))],
        ]);

        $old = $user->directPermissions()->pluck('name')->all();
        $user->directPermissions()->sync(Permission::whereIn('name', $validated['modules'])->pluck('id'));

        AuditLog::record('user.modules_updated', $user, ['modules' => $old], ['modules' => $validated['modules']]);

        return UserResource::make($user->load('roles.permissions', 'directPermissions'));
    }
}

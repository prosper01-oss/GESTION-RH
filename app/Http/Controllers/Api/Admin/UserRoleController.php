<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserRoleController extends Controller
{
    /**
     * Remplace les rôles d'un compte.
     */
    public function update(Request $request, User $user): UserResource
    {
        Gate::authorize(PermissionName::ManageUsers->value);

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['distinct', Rule::enum(RoleName::class)],
        ]);

        if ($user->is($request->user()) && ! in_array(RoleName::Administrateur->value, $validated['roles'], true)) {
            throw ValidationException::withMessages(['roles' => 'Vous ne pouvez pas retirer votre propre rôle d\'administrateur.']);
        }

        $oldRoles = $user->roles()->pluck('name')->all();
        $user->roles()->sync(Role::whereIn('name', $validated['roles'])->pluck('id'));

        AuditLog::record('user.roles_updated', $user, ['roles' => $oldRoles], ['roles' => $validated['roles']]);

        return UserResource::make($user->load('roles.permissions'));
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Liste paginée des comptes, filtrable par nom ou email.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::ManageUsers->value);

        $users = User::query()
            ->with('roles.permissions')
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Crée un compte avec un mot de passe provisoire, communiqué une seule fois dans la réponse.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $temporaryPassword = Str::password(12, symbols: false);

        $user = DB::transaction(function () use ($request, $temporaryPassword): User {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'employee_id' => $request->employee()?->id,
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ]);
            $user->roles()->sync(Role::whereIn('name', $request->validated('roles'))->pluck('id'));

            AuditLog::record('user.created', $user, newValues: [
                'email' => $user->email,
                'employee_id' => $user->employee_id,
                'roles' => $request->validated('roles'),
            ]);

            return $user;
        });

        return UserResource::make($user->load('roles.permissions'))
            ->additional(['temporary_password' => $temporaryPassword])
            ->response()
            ->setStatusCode(201);
    }
}

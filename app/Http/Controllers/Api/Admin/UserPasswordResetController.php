<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UserPasswordResetController extends Controller
{
    /**
     * Attribue un nouveau mot de passe provisoire, à changer à la connexion suivante.
     */
    public function store(User $user): JsonResponse
    {
        Gate::authorize(PermissionName::ManageUsers->value);

        $temporaryPassword = Str::password(12, symbols: false);

        $user->update([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ]);

        AuditLog::record('user.password_reset', $user);

        return response()->json([
            'message' => 'Mot de passe réinitialisé.',
            'temporary_password' => $temporaryPassword,
        ]);
    }
}

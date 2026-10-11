<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    /**
     * Ouvre une session (cookie Sanctum) pour un compte actif.
     */
    public function store(Request $request): UserResource
    {
        abort_unless($request->hasSession(), 400, "La connexion doit se faire depuis l'application (session Sanctum).");

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials)) {
            AuditLog::record('auth.login_failed', newValues: ['email' => $credentials['email']]);

            throw ValidationException::withMessages(['email' => 'Adresse email ou mot de passe incorrect.']);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages(['email' => 'Ce compte est désactivé.']);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('auth.login', $user, actor: $user);

        return UserResource::make($user->load('roles.permissions'));
    }

    /**
     * Ferme la session.
     */
    public function destroy(Request $request): JsonResponse
    {
        AuditLog::record('auth.logout', $request->user());

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Déconnexion effectuée.']);
    }
}

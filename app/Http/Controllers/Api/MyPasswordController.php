<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class MyPasswordController extends Controller
{
    /**
     * Change le mot de passe de l'utilisateur connecté (obligatoire à la première connexion).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(10)->letters()->numbers()],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        AuditLog::record('auth.password_changed', $request->user());

        return response()->json(['message' => 'Mot de passe modifié.']);
    }
}

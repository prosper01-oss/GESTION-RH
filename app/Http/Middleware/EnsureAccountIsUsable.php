<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse l'accès aux comptes désactivés et, tant que le mot de passe provisoire
 * n'a pas été changé, à toute route autre que celles nécessaires pour le changer.
 */
class EnsureAccountIsUsable
{
    /**
     * Routes accessibles avant le changement du mot de passe provisoire.
     *
     * @var list<string>
     */
    private const ROUTES_BEFORE_PASSWORD_CHANGE = ['me.show', 'me.password.update', 'logout'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            return response()->json(['message' => 'Ce compte est désactivé.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->must_change_password && ! $request->routeIs(self::ROUTES_BEFORE_PASSWORD_CHANGE)) {
            return response()->json([
                'message' => 'Vous devez changer votre mot de passe avant de continuer.',
                'code' => 'password_change_required',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

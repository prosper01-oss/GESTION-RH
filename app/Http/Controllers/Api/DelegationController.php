<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Suppléances (EF-53) : un Médecin-Chef ou un agent RH désigne un suppléant pour une période.
 */
class DelegationController extends Controller
{
    /**
     * Suppléances données et reçues par l'utilisateur connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $delegations = Delegation::query()
            ->with('delegator', 'delegate')
            ->where(fn ($query) => $query->where('delegator_id', $user->id)->orWhere('delegate_id', $user->id))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $delegations->map(fn (Delegation $delegation): array => $this->present($delegation, $user)),
            'delegable_roles' => $this->delegableRoles($user)->map(fn (RoleName $role): array => ['value' => $role->value, 'label' => $role->label()])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $this->delegableRoles($user);

        abort_if($roles->isEmpty(), 403, 'Votre rôle ne permet pas de désigner un suppléant.');

        $validated = $request->validate([
            'role' => ['required', Rule::in($roles->map->value->all())],
            'delegate_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true), Rule::notIn([$user->id])],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.now()->addYear()->toDateString()],
        ], [
            'delegate_id.not_in' => 'Vous ne pouvez pas être votre propre suppléant.',
            'delegate_id.exists' => 'Ce compte est introuvable ou désactivé.',
        ]);

        validator($validated)->after(function (Validator $validator) use ($validated, $user): void {
            $overlaps = Delegation::query()
                ->where('delegator_id', $user->id)
                ->where('role', $validated['role'])
                ->whereNull('revoked_at')
                ->whereDate('start_date', '<=', $validated['end_date'])
                ->whereDate('end_date', '>=', $validated['start_date'])
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('start_date', 'Une suppléance existe déjà sur cette période.');
            }
        })->validate();

        $delegation = Delegation::create($validated + ['delegator_id' => $user->id, 'created_by' => $user->id]);
        AuditLog::record('delegation.created', $delegation, newValues: $validated);

        return response()->json(['data' => $this->present($delegation->load('delegator', 'delegate'), $user)], 201);
    }

    /**
     * Met fin à une suppléance avant son terme.
     */
    public function destroy(Request $request, Delegation $delegation): JsonResponse
    {
        abort_unless($delegation->delegator_id === $request->user()->id, 403, 'Seule la personne remplacée peut mettre fin à la suppléance.');
        abort_if($delegation->revoked_at !== null, 409, 'Cette suppléance est déjà terminée.');

        $delegation->update(['revoked_at' => now()]);
        AuditLog::record('delegation.revoked', $delegation);

        return response()->json(['message' => 'Suppléance terminée.']);
    }

    /**
     * Comptes actifs pouvant être désignés suppléants (nom seulement).
     */
    public function candidates(Request $request): JsonResponse
    {
        abort_if($this->delegableRoles($request->user())->isEmpty(), 403);

        return response()->json([
            'data' => User::query()
                ->where('is_active', true)
                ->whereKeyNot($request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * @return Collection<int, RoleName>
     */
    private function delegableRoles(User $user): Collection
    {
        return collect([RoleName::MedecinChef, RoleName::RessourcesHumaines])->filter(fn (RoleName $role): bool => $user->hasRole($role));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Delegation $delegation, User $user): array
    {
        $today = now()->toDateString();

        return [
            'id' => $delegation->id,
            'role' => $delegation->role,
            'role_label' => $delegation->role->label(),
            'delegator' => $delegation->delegator->name,
            'delegate' => $delegation->delegate->name,
            'start_date' => $delegation->start_date->toDateString(),
            'end_date' => $delegation->end_date->toDateString(),
            'revoked_at' => $delegation->revoked_at,
            'is_current' => $delegation->revoked_at === null && $delegation->start_date->toDateString() <= $today && $delegation->end_date->toDateString() >= $today,
            'can_revoke' => $delegation->delegator_id === $user->id && $delegation->revoked_at === null && $delegation->end_date->toDateString() >= $today,
        ];
    }
}

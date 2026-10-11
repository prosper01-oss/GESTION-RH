<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Delegation;
use App\Models\EmployeeRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Destinataires des notifications : l'agent, les RH ou le Médecin-Chef, suppléants en cours compris.
 * L'auteur de la demande n'est jamais destinataire d'une notification destinée aux validateurs.
 */
class RequestRecipients
{
    /**
     * @return Collection<int, User>
     */
    public function reviewers(?EmployeeRequest $exceptOwnerOf = null): Collection
    {
        return $this->holders(PermissionName::ReviewRequests, RoleName::RessourcesHumaines, $exceptOwnerOf);
    }

    /**
     * @return Collection<int, User>
     */
    public function deciders(?EmployeeRequest $exceptOwnerOf = null): Collection
    {
        return $this->holders(PermissionName::DecideRequests, RoleName::MedecinChef, $exceptOwnerOf);
    }

    public function owner(EmployeeRequest $request): ?User
    {
        return User::query()->where('employee_id', $request->employee_id)->where('is_active', true)->first();
    }

    /**
     * @return Collection<int, User>
     */
    private function holders(PermissionName $permission, RoleName $role, ?EmployeeRequest $exceptOwnerOf): Collection
    {
        $direct = User::query()
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', $permission->value))
            ->get();

        $delegateIds = Delegation::query()->current()->where('role', $role->value)->pluck('delegate_id');
        $delegates = User::query()->where('is_active', true)->whereKey($delegateIds)->get();

        return $direct->merge($delegates)
            ->unique('id')
            ->reject(fn (User $user): bool => $exceptOwnerOf !== null && $exceptOwnerOf->isOwnedBy($user))
            ->values();
    }
}

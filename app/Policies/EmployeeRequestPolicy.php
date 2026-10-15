<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use App\Models\User;

class EmployeeRequestPolicy
{
    /**
     * Voir les demandes de tous les agents.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::ReviewRequests) || $user->hasPermission(PermissionName::DecideRequests);
    }

    public function view(User $user, EmployeeRequest $request): bool
    {
        return $request->isOwnedBy($user) || $this->viewAny($user);
    }

    /**
     * Un agent rattaché à une fiche peut déposer ses propres demandes.
     * Les RH peuvent en créer pour n'importe quel agent.
     */
    public function create(User $user): bool
    {
        return $user->employee_id !== null || $user->hasPermission(PermissionName::ReviewRequests);
    }

    public function update(User $user, EmployeeRequest $request): bool
    {
        return $request->isOwnedBy($user) && $request->status->isEditableByOwner();
    }

    public function delete(User $user, EmployeeRequest $request): bool
    {
        return $request->isOwnedBy($user) && $request->status === RequestStatus::Brouillon;
    }

    /**
     * Gestes des RH sur une demande finalisée : interruption d'un congé, départ et retour d'une sortie.
     */
    public function process(User $user, EmployeeRequest $request): bool
    {
        return ! $request->isOwnedBy($user) && $user->hasPermission(PermissionName::ReviewRequests);
    }
}

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
     * Seul un compte rattaché à une fiche employé dépose des demandes.
     */
    public function create(User $user): bool
    {
        return $user->employee_id !== null;
    }

    public function update(User $user, EmployeeRequest $request): bool
    {
        return $request->isOwnedBy($user) && $request->status->isEditableByOwner();
    }

    public function delete(User $user, EmployeeRequest $request): bool
    {
        return $request->isOwnedBy($user) && $request->status === RequestStatus::Brouillon;
    }

    public function interrupt(User $user, EmployeeRequest $request): bool
    {
        return ! $request->isOwnedBy($user) && $user->hasPermission(PermissionName::ReviewRequests);
    }
}

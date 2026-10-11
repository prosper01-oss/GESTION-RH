<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /**
     * Consulter l'annuaire du personnel.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::ViewEmployees);
    }

    /**
     * Consulter une fiche : la sienne, ou toutes avec la permission.
     */
    public function view(User $user, Employee $employee): bool
    {
        return $user->employee_id === $employee->id
            || $user->hasPermission(PermissionName::ViewEmployees);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::ManageEmployees);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission(PermissionName::ManageEmployees);
    }
}

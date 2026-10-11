<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    /**
     * La liste des services est nécessaire à tous les utilisateurs (formulaires, filtres).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Department $department): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::ManageDepartments);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasPermission(PermissionName::ManageDepartments);
    }
}

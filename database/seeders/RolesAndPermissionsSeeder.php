<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Crée ou met à jour les rôles et les permissions de la matrice des droits.
 * Peut être relancé sans risque après chaque ajout de permission.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionIds = collect(PermissionName::cases())->mapWithKeys(fn (PermissionName $permission): array => [
            $permission->value => Permission::updateOrCreate(
                ['name' => $permission->value],
                ['description' => $permission->description()],
            )->id,
        ]);

        foreach (RoleName::cases() as $roleName) {
            $role = Role::updateOrCreate(['name' => $roleName->value], ['description' => $roleName->label()]);

            $role->permissions()->sync(
                collect($roleName->permissions())->map(fn (PermissionName $permission): int => $permissionIds[$permission->value])->all()
            );
        }
    }
}

<?php

namespace Tests;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Les rôles et permissions sont des données de référence nécessaires à chaque test.
     */
    protected bool $seed = true;

    /**
     * Seuls les rôles sont chargés : chaque test crée les paramètres de congés dont il a besoin.
     */
    protected string $seeder = RolesAndPermissionsSeeder::class;

    /**
     * Crée un compte actif ayant le rôle donné.
     */
    protected function userWithRole(RoleName $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}

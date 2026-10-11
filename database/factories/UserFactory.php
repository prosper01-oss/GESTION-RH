<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Attribue un rôle au compte. Les rôles doivent exister (RolesAndPermissionsSeeder).
     */
    public function withRole(RoleName $role): static
    {
        return $this->afterCreating(function ($user) use ($role): void {
            $user->roles()->attach(Role::where('name', $role->value)->firstOrFail());
        });
    }

    /**
     * Rattache le compte à une fiche employé, avec le rôle Personnel.
     */
    public function forEmployee(?Employee $employee = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'employee_id' => $employee ?? Employee::factory(),
        ])->withRole(RoleName::Personnel);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes): array => [
            'must_change_password' => true,
        ]);
    }
}

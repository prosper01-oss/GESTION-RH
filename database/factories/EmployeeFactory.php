<?php

namespace Database\Factories;

use App\Enums\ContractType;
use App\Enums\EmployeeStatus;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matricule' => fake()->unique()->numerify('OMIT-#####'),
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'department_id' => Department::factory(),
            'position' => fake()->jobTitle(),
            'hire_date' => fake()->dateTimeBetween('-15 years', '-1 month')->format('Y-m-d'),
            'contract_type' => ContractType::Cdi,
            'status' => EmployeeStatus::Actif,
            'phone_pro' => fake()->numerify('+261 34 ## ### ##'),
            'email_pro' => fake()->unique()->safeEmail(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmployeeStatus::Inactif,
        ]);
    }
}

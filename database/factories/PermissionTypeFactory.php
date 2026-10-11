<?php

namespace Database\Factories;

use App\Models\PermissionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermissionType>
 */
class PermissionTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Permission '.fake()->unique()->words(2, true),
            'is_paid' => true,
            'requires_attachment' => false,
            'is_medical' => false,
            'max_duration_hours' => null,
            'is_active' => true,
        ];
    }
}

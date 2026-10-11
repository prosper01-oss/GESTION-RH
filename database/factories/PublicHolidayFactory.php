<?php

namespace Database\Factories;

use App\Models\PublicHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicHoliday>
 */
class PublicHolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
            'label' => 'Jour férié '.fake()->word(),
            'is_recurring' => false,
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_recurring' => true,
        ]);
    }
}

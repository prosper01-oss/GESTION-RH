<?php

namespace Database\Factories;

use App\Enums\AccrualRule;
use App\Enums\CountingMode;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('T??#'),
            'name' => 'Congé '.fake()->unique()->words(2, true),
            'is_paid' => true,
            'has_balance' => true,
            'requires_attachment' => false,
            'is_medical' => false,
            'allows_half_day' => false,
            'is_active' => true,
        ];
    }

    /**
     * Ajoute une version des règles en vigueur depuis le 1er janvier 2000.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withPolicy(array $attributes = []): static
    {
        return $this->afterCreating(function (LeaveType $leaveType) use ($attributes): void {
            $leaveType->policies()->create($attributes + [
                'accrual_rule' => AccrualRule::Mensuelle,
                'annual_entitlement' => 30,
                'counting_mode' => CountingMode::Calendaire,
                'carry_over_max' => null,
                'effective_from' => '2000-01-01',
            ]);
        });
    }

    public function withoutBalance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'has_balance' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}

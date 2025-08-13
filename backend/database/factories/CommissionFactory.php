<?php

namespace Database\Factories;

use App\Models\Commission;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomFloat(2, 100, 2000), // Between 100 and 2000 euros
            'is_active' => $this->faker->boolean(),
            'description' => $this->faker->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function defaultAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => 500.00,
            'is_active' => true,
            'description' => 'Default commission rate',
        ]);
    }

    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }
}
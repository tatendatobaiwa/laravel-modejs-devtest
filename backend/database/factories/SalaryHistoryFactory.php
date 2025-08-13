<?php

namespace Database\Factories;

use App\Models\SalaryHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalaryHistory>
 */
class SalaryHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = SalaryHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $oldSalaryEuros = fake()->randomFloat(2, 30000, 100000);
        $newSalaryEuros = fake()->randomFloat(2, 35000, 120000);
        $oldCommission = 500.00;
        $newCommission = fake()->randomFloat(2, 400, 800);

        return [
            'user_id' => User::factory(),
            'old_salary_local_currency' => fake()->randomFloat(2, 35000, 120000),
            'new_salary_local_currency' => fake()->randomFloat(2, 40000, 140000),
            'old_salary_euros' => $oldSalaryEuros,
            'new_salary_euros' => $newSalaryEuros,
            'old_commission' => $oldCommission,
            'new_commission' => $newCommission,
            'old_displayed_salary' => $oldSalaryEuros + $oldCommission,
            'new_displayed_salary' => $newSalaryEuros + $newCommission,
            'changed_by' => User::factory(),
            'change_reason' => fake()->sentence(),
            'change_type' => fake()->randomElement(['salary_change', 'commission_change', 'general_update']),
            'metadata' => [
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'timestamp' => now()->toISOString(),
            ],
            'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'updated_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * Create a salary increase history.
     */
    public function salaryIncrease(): static
    {
        return $this->state(function (array $attributes) {
            $oldSalary = fake()->randomFloat(2, 30000, 80000);
            $newSalary = $oldSalary + fake()->randomFloat(2, 5000, 20000);
            $commission = $attributes['old_commission'] ?? 500.00;

            return [
                'old_salary_euros' => $oldSalary,
                'new_salary_euros' => $newSalary,
                'old_displayed_salary' => $oldSalary + $commission,
                'new_displayed_salary' => $newSalary + $commission,
                'change_type' => 'salary_change',
                'change_reason' => 'Annual salary increase',
            ];
        });
    }

    /**
     * Create a salary decrease history.
     */
    public function salaryDecrease(): static
    {
        return $this->state(function (array $attributes) {
            $oldSalary = fake()->randomFloat(2, 50000, 120000);
            $newSalary = $oldSalary - fake()->randomFloat(2, 5000, 15000);
            $commission = $attributes['old_commission'] ?? 500.00;

            return [
                'old_salary_euros' => $oldSalary,
                'new_salary_euros' => $newSalary,
                'old_displayed_salary' => $oldSalary + $commission,
                'new_displayed_salary' => $newSalary + $commission,
                'change_type' => 'salary_change',
                'change_reason' => 'Salary adjustment',
            ];
        });
    }

    /**
     * Create a commission change history.
     */
    public function commissionChange(): static
    {
        return $this->state(function (array $attributes) {
            $salaryEuros = $attributes['old_salary_euros'] ?? fake()->randomFloat(2, 40000, 100000);
            $oldCommission = 500.00;
            $newCommission = fake()->randomFloat(2, 300, 1000);

            return [
                'old_salary_euros' => $salaryEuros,
                'new_salary_euros' => $salaryEuros,
                'old_commission' => $oldCommission,
                'new_commission' => $newCommission,
                'old_displayed_salary' => $salaryEuros + $oldCommission,
                'new_displayed_salary' => $salaryEuros + $newCommission,
                'change_type' => 'commission_change',
                'change_reason' => 'Commission adjustment',
            ];
        });
    }

    /**
     * Create history for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create history changed by a specific user.
     */
    public function changedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'changed_by' => $user->id,
        ]);
    }

    /**
     * Create history with specific change reason.
     */
    public function withReason(string $reason): static
    {
        return $this->state(fn (array $attributes) => [
            'change_reason' => $reason,
        ]);
    }

    /**
     * Create history with specific change type.
     */
    public function withChangeType(string $changeType): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => $changeType,
        ]);
    }

    /**
     * Create recent history (within last 30 days).
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'updated_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Create old history (older than 6 months).
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-2 years', '-6 months'),
            'updated_at' => fake()->dateTimeBetween('-2 years', '-6 months'),
        ]);
    }
}
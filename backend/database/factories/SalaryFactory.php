<?php

namespace Database\Factories;

use App\Models\Salary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Salary>
 */
class SalaryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Salary::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $salaryLocal = fake()->randomFloat(2, 30000, 150000);
        $currencyCode = fake()->randomElement(['USD', 'EUR', 'GBP', 'CAD', 'AUD']);
        $exchangeRates = [
            'USD' => 0.85,
            'EUR' => 1.00,
            'GBP' => 1.15,
            'CAD' => 0.65,
            'AUD' => 0.60,
        ];
        $salaryEuros = round($salaryLocal * $exchangeRates[$currencyCode], 2);
        $commission = 500.00;

        return [
            'user_id' => User::factory(),
            'salary_local_currency' => $salaryLocal,
            'local_currency_code' => $currencyCode,
            'salary_euros' => $salaryEuros,
            'commission' => $commission,
            'displayed_salary' => $salaryEuros + $commission,
            'effective_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'document_path' => null,
            'notes' => fake()->optional()->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Create a salary with specific currency.
     */
    public function withCurrency(string $currencyCode, float $exchangeRate = null): static
    {
        $exchangeRates = [
            'USD' => 0.85,
            'EUR' => 1.00,
            'GBP' => 1.15,
            'CAD' => 0.65,
            'AUD' => 0.60,
            'JPY' => 0.0065,
        ];

        $rate = $exchangeRate ?? $exchangeRates[$currencyCode] ?? 1.00;

        return $this->state(function (array $attributes) use ($currencyCode, $rate) {
            $salaryLocal = $attributes['salary_local_currency'] ?? fake()->randomFloat(2, 30000, 150000);
            $salaryEuros = round($salaryLocal * $rate, 2);
            $commission = $attributes['commission'] ?? 500.00;

            return [
                'local_currency_code' => $currencyCode,
                'salary_local_currency' => $salaryLocal,
                'salary_euros' => $salaryEuros,
                'displayed_salary' => $salaryEuros + $commission,
            ];
        });
    }

    /**
     * Create a salary with specific commission.
     */
    public function withCommission(float $commission): static
    {
        return $this->state(function (array $attributes) use ($commission) {
            $salaryEuros = $attributes['salary_euros'] ?? 50000.00;
            
            return [
                'commission' => $commission,
                'displayed_salary' => $salaryEuros + $commission,
            ];
        });
    }

    /**
     * Create a salary with specific amounts.
     */
    public function withAmounts(float $salaryLocal, float $salaryEuros, float $commission = 500.00): static
    {
        return $this->state(fn (array $attributes) => [
            'salary_local_currency' => $salaryLocal,
            'salary_euros' => $salaryEuros,
            'commission' => $commission,
            'displayed_salary' => $salaryEuros + $commission,
        ]);
    }

    /**
     * Create a salary for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a salary with notes.
     */
    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes,
        ]);
    }

    /**
     * Create a salary with document path.
     */
    public function withDocument(string $documentPath): static
    {
        return $this->state(fn (array $attributes) => [
            'document_path' => $documentPath,
        ]);
    }

    /**
     * Create a high salary (above 100k EUR).
     */
    public function highSalary(): static
    {
        return $this->state(function (array $attributes) {
            $salaryEuros = fake()->randomFloat(2, 100000, 300000);
            $commission = $attributes['commission'] ?? 500.00;

            return [
                'salary_euros' => $salaryEuros,
                'displayed_salary' => $salaryEuros + $commission,
            ];
        });
    }

    /**
     * Create a low salary (below 30k EUR).
     */
    public function lowSalary(): static
    {
        return $this->state(function (array $attributes) {
            $salaryEuros = fake()->randomFloat(2, 15000, 29999);
            $commission = $attributes['commission'] ?? 500.00;

            return [
                'salary_euros' => $salaryEuros,
                'displayed_salary' => $salaryEuros + $commission,
            ];
        });
    }
}
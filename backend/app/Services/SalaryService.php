<?php

namespace App\Services;

use App\Models\Salary;
use App\Models\User;
use App\Models\SalaryHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SalaryService
{
    /**
     * Default commission value in euros.
     */
    const DEFAULT_COMMISSION = 500.00;

    /**
     * Minimum salary threshold in euros.
     */
    const MIN_SALARY_EUROS = 1000.00;

    /**
     * Maximum salary threshold in euros.
     */
    const MAX_SALARY_EUROS = 500000.00;

    /**
     * Supported currency codes with their exchange rates to EUR.
     * In production, this would come from an external API.
     */
    const EXCHANGE_RATES = [
        'USD' => 0.85,
        'GBP' => 1.15,
        'EUR' => 1.00,
        'CAD' => 0.65,
        'AUD' => 0.60,
        'JPY' => 0.0065,
        'CHF' => 0.95,
        'SEK' => 0.085,
        'NOK' => 0.082,
        'DKK' => 0.134,
    ];

    /**
     * Create or update a salary record for a user.
     */
    public function createOrUpdateSalary(
        User $user,
        float $salaryLocalCurrency,
        string $localCurrencyCode = 'EUR',
        ?float $commission = null,
        ?string $notes = null,
        ?int $changedBy = null
    ): Salary {
        $this->validateSalaryData($salaryLocalCurrency, $localCurrencyCode, $commission);

        return DB::transaction(function () use (
            $user,
            $salaryLocalCurrency,
            $localCurrencyCode,
            $commission,
            $notes,
            $changedBy
        ) {
            $commission = $commission ?? self::DEFAULT_COMMISSION;
            $salaryEuros = $this->convertToEuros($salaryLocalCurrency, $localCurrencyCode);
            $displayedSalary = $this->calculateDisplayedSalary($salaryEuros, $commission);

            // Store original values for history tracking
            $originalValues = [];
            $existingSalary = $user->salary;
            
            if ($existingSalary) {
                $originalValues = $existingSalary->toArray();
            }

            $salaryData = [
                'user_id' => $user->id,
                'salary_local_currency' => $salaryLocalCurrency,
                'local_currency_code' => strtoupper($localCurrencyCode),
                'salary_euros' => $salaryEuros,
                'commission' => $commission,
                'displayed_salary' => $displayedSalary,
                'effective_date' => now()->toDateString(),
                'notes' => $notes,
            ];

            $salary = $user->salary()->updateOrCreate(
                ['user_id' => $user->id],
                $salaryData
            );

            // Create history record if this was an update
            if ($existingSalary && !empty($originalValues)) {
                $this->createHistoryRecord($salary, $originalValues, $changedBy);
            }

            Log::info('Salary updated', [
                'user_id' => $user->id,
                'salary_euros' => $salaryEuros,
                'commission' => $commission,
                'displayed_salary' => $displayedSalary,
                'changed_by' => $changedBy ?? auth()->id(),
            ]);

            return $salary->fresh();
        });
    }

    /**
     * Update commission for a salary record.
     */
    public function updateCommission(
        Salary $salary,
        float $commission,
        ?int $changedBy = null,
        ?string $reason = null
    ): Salary {
        $this->validateCommission($commission);

        return DB::transaction(function () use ($salary, $commission, $changedBy, $reason) {
            $originalValues = $salary->toArray();
            
            $salary->update([
                'commission' => $commission,
                'displayed_salary' => $this->calculateDisplayedSalary($salary->salary_euros, $commission),
            ]);

            $this->createHistoryRecord(
                $salary,
                $originalValues,
                $changedBy,
                $reason ?? 'Commission updated'
            );

            Log::info('Commission updated', [
                'salary_id' => $salary->id,
                'user_id' => $salary->user_id,
                'old_commission' => $originalValues['commission'],
                'new_commission' => $commission,
                'changed_by' => $changedBy ?? auth()->id(),
            ]);

            return $salary->fresh();
        });
    }

    /**
     * Convert local currency amount to euros.
     */
    public function convertToEuros(float $amount, string $currencyCode): float
    {
        $currencyCode = strtoupper($currencyCode);
        
        if (!isset(self::EXCHANGE_RATES[$currencyCode])) {
            throw new InvalidArgumentException("Unsupported currency code: {$currencyCode}");
        }

        $rate = self::EXCHANGE_RATES[$currencyCode];
        return round($amount * $rate, 2);
    }

    /**
     * Calculate displayed salary (salary in euros + commission).
     */
    public function calculateDisplayedSalary(float $salaryEuros, float $commission): float
    {
        return round($salaryEuros + $commission, 2);
    }

    /**
     * Get current exchange rate for a currency.
     */
    public function getExchangeRate(string $currencyCode): float
    {
        $currencyCode = strtoupper($currencyCode);
        
        if (!isset(self::EXCHANGE_RATES[$currencyCode])) {
            throw new InvalidArgumentException("Unsupported currency code: {$currencyCode}");
        }

        return self::EXCHANGE_RATES[$currencyCode];
    }

    /**
     * Get all supported currency codes.
     */
    public function getSupportedCurrencies(): array
    {
        return array_keys(self::EXCHANGE_RATES);
    }

    /**
     * Validate salary data before processing.
     */
    private function validateSalaryData(
        float $salaryLocalCurrency,
        string $localCurrencyCode,
        ?float $commission = null
    ): void {
        if ($salaryLocalCurrency <= 0) {
            throw new InvalidArgumentException('Salary must be greater than zero');
        }

        $currencyCode = strtoupper($localCurrencyCode);
        if (!isset(self::EXCHANGE_RATES[$currencyCode])) {
            throw new InvalidArgumentException("Unsupported currency code: {$currencyCode}");
        }

        // Convert to euros for validation
        $salaryEuros = $this->convertToEuros($salaryLocalCurrency, $currencyCode);
        
        if ($salaryEuros < self::MIN_SALARY_EUROS) {
            throw new InvalidArgumentException(
                "Salary must be at least €" . number_format(self::MIN_SALARY_EUROS, 2) . 
                " (equivalent in {$currencyCode})"
            );
        }

        if ($salaryEuros > self::MAX_SALARY_EUROS) {
            throw new InvalidArgumentException(
                "Salary cannot exceed €" . number_format(self::MAX_SALARY_EUROS, 2) . 
                " (equivalent in {$currencyCode})"
            );
        }

        if ($commission !== null) {
            $this->validateCommission($commission);
        }
    }

    /**
     * Validate commission amount.
     */
    private function validateCommission(float $commission): void
    {
        if ($commission < 0) {
            throw new InvalidArgumentException('Commission cannot be negative');
        }

        if ($commission > 50000) {
            throw new InvalidArgumentException('Commission cannot exceed €50,000');
        }
    }

    /**
     * Create a salary history record.
     */
    private function createHistoryRecord(
        Salary $salary,
        array $originalValues,
        ?int $changedBy = null,
        ?string $reason = null
    ): void {
        SalaryHistory::createFromSalaryChange(
            $salary,
            $originalValues,
            $changedBy ?? auth()->id(),
            $reason ?? 'Salary updated via admin panel'
        );
    }

    /**
     * Get salary statistics for reporting.
     */
    public function getSalaryStatistics(): array
    {
        $salaries = Salary::with('user')->get();

        if ($salaries->isEmpty()) {
            return [
                'total_employees' => 0,
                'average_salary_euros' => 0,
                'median_salary_euros' => 0,
                'min_salary_euros' => 0,
                'max_salary_euros' => 0,
                'total_commission_paid' => 0,
                'average_commission' => 0,
                'currency_distribution' => [],
            ];
        }

        $salaryEuros = $salaries->pluck('salary_euros')->sort()->values();
        $commissions = $salaries->pluck('commission');
        $currencies = $salaries->pluck('local_currency_code');

        return [
            'total_employees' => $salaries->count(),
            'average_salary_euros' => round($salaryEuros->average(), 2),
            'median_salary_euros' => $this->calculateMedian($salaryEuros->toArray()),
            'min_salary_euros' => $salaryEuros->min(),
            'max_salary_euros' => $salaryEuros->max(),
            'total_commission_paid' => round($commissions->sum(), 2),
            'average_commission' => round($commissions->average(), 2),
            'currency_distribution' => $currencies->countBy()->toArray(),
        ];
    }

    /**
     * Calculate median value from an array of numbers.
     */
    private function calculateMedian(array $numbers): float
    {
        sort($numbers);
        $count = count($numbers);
        
        if ($count === 0) {
            return 0;
        }
        
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($numbers[$middle - 1] + $numbers[$middle]) / 2;
        }
        
        return $numbers[$middle];
    }

    /**
     * Bulk update salaries with validation.
     */
    public function bulkUpdateSalaries(array $updates, ?int $changedBy = null): array
    {
        $results = [];
        
        DB::transaction(function () use ($updates, $changedBy, &$results) {
            foreach ($updates as $update) {
                try {
                    $user = User::findOrFail($update['user_id']);
                    
                    $salary = $this->createOrUpdateSalary(
                        $user,
                        $update['salary_local_currency'],
                        $update['local_currency_code'] ?? 'EUR',
                        $update['commission'] ?? null,
                        $update['notes'] ?? null,
                        $changedBy
                    );
                    
                    $results[] = [
                        'user_id' => $user->id,
                        'success' => true,
                        'salary_id' => $salary->id,
                        'message' => 'Salary updated successfully',
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'user_id' => $update['user_id'] ?? null,
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                    
                    Log::error('Bulk salary update failed', [
                        'update_data' => $update,
                        'error' => $e->getMessage(),
                        'changed_by' => $changedBy,
                    ]);
                }
            }
        });
        
        return $results;
    }
}
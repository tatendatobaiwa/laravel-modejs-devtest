<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salary extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'salary_local_currency',
        'local_currency_code',
        'salary_euros',
        'commission',
        'displayed_salary',
        'effective_date',
        'document_path',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'salary_local_currency' => 'decimal:2',
        'salary_euros' => 'decimal:2',
        'commission' => 'decimal:2',
        'displayed_salary' => 'decimal:2',
        'effective_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Default commission value in euros.
     */
    const DEFAULT_COMMISSION = 500.00;

    /**
     * Boot the model and set up event listeners.
     */
    protected static function boot()
    {
        parent::boot();

        // Set default commission if not provided
        static::creating(function ($salary) {
            if (is_null($salary->commission)) {
                $salary->commission = self::DEFAULT_COMMISSION;
            }
            
            // Set effective date if not provided
            if (is_null($salary->effective_date)) {
                $salary->effective_date = now()->toDateString();
            }

            // Calculate displayed salary
            $salary->displayed_salary = $salary->calculateDisplayedSalary();
        });

        // Recalculate displayed salary on updates
        static::updating(function ($salary) {
            if (is_null($salary->commission)) {
                $salary->commission = self::DEFAULT_COMMISSION;
            }

            $salary->displayed_salary = $salary->calculateDisplayedSalary();
        });

        // Create salary history record after update
        static::updated(function ($salary) {
            $salary->createHistoryRecord();
        });
    }

    /**
     * Get the user that owns the salary.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate the displayed salary (salary_euros + commission).
     */
    public function calculateDisplayedSalary(): float
    {
        $salaryEuros = $this->salary_euros ?? 0;
        $commission = $this->commission ?? self::DEFAULT_COMMISSION;
        
        return round($salaryEuros + $commission, 2);
    }

    /**
     * Convert local currency to euros using a simple conversion rate.
     * In a real application, this would use an external API or database rates.
     */
    public function convertToEuros(?float $exchangeRate = null): void
    {
        if (!$this->salary_local_currency) {
            return;
        }

        // Default exchange rate (this should come from a service in production)
        $rate = $exchangeRate ?? $this->getExchangeRate();
        
        $this->salary_euros = round($this->salary_local_currency * $rate, 2);
    }

    /**
     * Get exchange rate for the local currency.
     * This is a simplified implementation - in production, use a currency service.
     */
    private function getExchangeRate(): float
    {
        $rates = [
            'USD' => 0.85,
            'GBP' => 1.15,
            'EUR' => 1.00,
            'CAD' => 0.65,
            'AUD' => 0.60,
            'JPY' => 0.0065,
        ];

        return $rates[$this->local_currency_code ?? 'EUR'] ?? 1.00;
    }

    /**
     * Create a history record for salary changes.
     */
    private function createHistoryRecord(): void
    {
        if (!$this->wasChanged()) {
            return;
        }

        $changes = $this->getChanges();
        $original = $this->getOriginal();

        SalaryHistory::create([
            'user_id' => $this->user_id,
            'old_salary_local_currency' => $original['salary_local_currency'] ?? null,
            'new_salary_local_currency' => $changes['salary_local_currency'] ?? $this->salary_local_currency,
            'old_salary_euros' => $original['salary_euros'] ?? null,
            'new_salary_euros' => $changes['salary_euros'] ?? $this->salary_euros,
            'old_commission' => $original['commission'] ?? null,
            'new_commission' => $changes['commission'] ?? $this->commission,
            'changed_by' => auth()->id(),
            'change_reason' => 'Salary updated via admin panel',
        ]);
    }

    /**
     * Get the displayed salary attribute (calculated value).
     */
    public function getDisplayedSalaryAttribute(): float
    {
        return $this->calculateDisplayedSalary();
    }

    /**
     * Get formatted local salary for display.
     */
    public function getFormattedLocalSalaryAttribute(): string
    {
        $currency = $this->local_currency_code ?? 'EUR';
        return $currency . ' ' . number_format($this->salary_local_currency, 2);
    }

    /**
     * Get formatted euro salary for display.
     */
    public function getFormattedEuroSalaryAttribute(): string
    {
        return '€' . number_format($this->salary_euros, 2);
    }

    /**
     * Get formatted commission for display.
     */
    public function getFormattedCommissionAttribute(): string
    {
        return '€' . number_format($this->commission, 2);
    }

    /**
     * Get formatted displayed salary for display.
     */
    public function getFormattedDisplayedSalaryAttribute(): string
    {
        return '€' . number_format($this->displayed_salary, 2);
    }

    /**
     * Scope to get salaries with user information.
     */
    public function scopeWithUser($query)
    {
        return $query->with('user:id,name,email,created_at');
    }

    /**
     * Scope for optimized admin queries.
     */
    public function scopeForAdmin($query)
    {
        return $query->select([
            'id', 'user_id', 'salary_local_currency', 'local_currency_code',
            'salary_euros', 'commission', 'displayed_salary', 'effective_date',
            'created_at', 'updated_at'
        ])->with('user:id,name,email');
    }

    /**
     * Scope for high-performance analytics queries.
     */
    public function scopeForAnalytics($query)
    {
        return $query->select([
            'salary_euros', 'commission', 'displayed_salary', 
            'local_currency_code', 'created_at'
        ]);
    }

    /**
     * Scope for salary range queries with index optimization.
     */
    public function scopeInRange($query, float $min, float $max)
    {
        return $query->whereBetween('salary_euros', [$min, $max])
                    ->orderBy('salary_euros');
    }

    /**
     * Scope for recent salary updates.
     */
    public function scopeRecentlyUpdated($query, int $days = 30)
    {
        return $query->where('updated_at', '>=', now()->subDays($days))
                    ->orderBy('updated_at', 'desc');
    }

    /**
     * Scope for statistics queries with minimal data.
     */
    public function scopeForStats($query)
    {
        return $query->select([
            'id', 'salary_euros', 'commission', 'local_currency_code', 'created_at'
        ]);
    }

    /**
     * Scope to get salaries by currency code.
     */
    public function scopeByCurrency($query, string $currencyCode)
    {
        return $query->where('local_currency_code', $currencyCode);
    }

    /**
     * Scope to get salaries within a date range.
     */
    public function scopeEffectiveBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('effective_date', [$startDate, $endDate]);
    }

    /**
     * Check if salary has been recently updated (within last 24 hours).
     */
    public function isRecentlyUpdated(): bool
    {
        return $this->updated_at && $this->updated_at->diffInHours(now()) < 24;
    }

    /**
     * Get the total compensation (salary + commission) in euros.
     */
    public function getTotalCompensationAttribute(): float
    {
        return $this->displayed_salary;
    }
}

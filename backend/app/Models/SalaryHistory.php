<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SalaryHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'salary_histories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'old_salary_local_currency',
        'new_salary_local_currency',
        'old_salary_euros',
        'new_salary_euros',
        'old_commission',
        'new_commission',
        'old_displayed_salary',
        'new_displayed_salary',
        'changed_by',
        'change_reason',
        'change_type',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'old_salary_local_currency' => 'decimal:2',
        'new_salary_local_currency' => 'decimal:2',
        'old_salary_euros' => 'decimal:2',
        'new_salary_euros' => 'decimal:2',
        'old_commission' => 'decimal:2',
        'new_commission' => 'decimal:2',
        'old_displayed_salary' => 'decimal:2',
        'new_displayed_salary' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     * We only need created_at for audit trail.
     */
    public $timestamps = true;

    /**
     * Boot the model and set up event listeners.
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent updates to history records (immutable)
        static::updating(function ($model) {
            return false;
        });

        // Prevent deletion of history records
        static::deleting(function ($model) {
            return false;
        });

        // Set change type automatically
        static::creating(function ($history) {
            if (!$history->change_type) {
                $history->change_type = $history->determineChangeType();
            }
        });
    }

    /**
     * Get the user that owns the salary history.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who made the change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Determine the type of change based on the values.
     */
    private function determineChangeType(): string
    {
        if ($this->old_salary_local_currency !== $this->new_salary_local_currency) {
            return 'salary_change';
        }
        
        if ($this->old_commission !== $this->new_commission) {
            return 'commission_change';
        }
        
        return 'general_update';
    }

    /**
     * Scope to get history for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get history ordered by most recent first.
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope to get history within a date range.
     */
    public function scopeBetweenDates(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get history by change type.
     */
    public function scopeByChangeType(Builder $query, string $changeType): Builder
    {
        return $query->where('change_type', $changeType);
    }

    /**
     * Scope to get history changed by a specific user.
     */
    public function scopeChangedBy(Builder $query, int $userId): Builder
    {
        return $query->where('changed_by', $userId);
    }

    /**
     * Scope to get salary increases only.
     */
    public function scopeSalaryIncreases(Builder $query): Builder
    {
        return $query->whereRaw('new_salary_euros > old_salary_euros');
    }

    /**
     * Scope to get salary decreases only.
     */
    public function scopeSalaryDecreases(Builder $query): Builder
    {
        return $query->whereRaw('new_salary_euros < old_salary_euros');
    }

    /**
     * Scope to get commission changes only.
     */
    public function scopeCommissionChanges(Builder $query): Builder
    {
        return $query->whereRaw('new_commission != old_commission');
    }

    /**
     * Scope to include related user and changed_by user data.
     */
    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with(['user:id,name,email', 'changedBy:id,name,email']);
    }

    /**
     * Scope for optimized admin queries with minimal data.
     */
    public function scopeForAdmin(Builder $query): Builder
    {
        return $query->select([
            'id', 'user_id', 'old_salary_euros', 'new_salary_euros',
            'old_commission', 'new_commission', 'changed_by', 'change_reason',
            'change_type', 'created_at'
        ])->with([
            'user:id,name,email',
            'changedBy:id,name,email'
        ]);
    }

    /**
     * Get the salary change amount in euros.
     */
    public function getSalaryChangeAmountAttribute(): ?float
    {
        if (is_null($this->old_salary_euros) || is_null($this->new_salary_euros)) {
            return null;
        }
        
        return round($this->new_salary_euros - $this->old_salary_euros, 2);
    }

    /**
     * Get the commission change amount in euros.
     */
    public function getCommissionChangeAmountAttribute(): ?float
    {
        if (is_null($this->old_commission) || is_null($this->new_commission)) {
            return null;
        }
        
        return round($this->new_commission - $this->old_commission, 2);
    }

    /**
     * Get the total compensation change amount.
     */
    public function getTotalChangeAmountAttribute(): ?float
    {
        if (is_null($this->old_displayed_salary) || is_null($this->new_displayed_salary)) {
            return null;
        }
        
        return round($this->new_displayed_salary - $this->old_displayed_salary, 2);
    }

    /**
     * Check if this was a salary increase.
     */
    public function isSalaryIncrease(): bool
    {
        return $this->salary_change_amount > 0;
    }

    /**
     * Check if this was a salary decrease.
     */
    public function isSalaryDecrease(): bool
    {
        return $this->salary_change_amount < 0;
    }

    /**
     * Get formatted salary change for display.
     */
    public function getFormattedSalaryChangeAttribute(): string
    {
        $amount = $this->salary_change_amount;
        
        if (is_null($amount)) {
            return 'N/A';
        }
        
        $prefix = $amount >= 0 ? '+' : '';
        return $prefix . '€' . number_format($amount, 2);
    }

    /**
     * Get formatted commission change for display.
     */
    public function getFormattedCommissionChangeAttribute(): string
    {
        $amount = $this->commission_change_amount;
        
        if (is_null($amount)) {
            return 'N/A';
        }
        
        $prefix = $amount >= 0 ? '+' : '';
        return $prefix . '€' . number_format($amount, 2);
    }

    /**
     * Get formatted total change for display.
     */
    public function getFormattedTotalChangeAttribute(): string
    {
        $amount = $this->total_change_amount;
        
        if (is_null($amount)) {
            return 'N/A';
        }
        
        $prefix = $amount >= 0 ? '+' : '';
        return $prefix . '€' . number_format($amount, 2);
    }

    /**
     * Get a summary of the changes made.
     */
    public function getChangeSummaryAttribute(): string
    {
        $changes = [];
        
        if ($this->salary_change_amount !== null && $this->salary_change_amount != 0) {
            $changes[] = 'Salary: ' . $this->formatted_salary_change;
        }
        
        if ($this->commission_change_amount !== null && $this->commission_change_amount != 0) {
            $changes[] = 'Commission: ' . $this->formatted_commission_change;
        }
        
        return implode(', ', $changes) ?: 'No changes';
    }

    /**
     * Create a comprehensive history record from salary changes.
     */
    public static function createFromSalaryChange(Salary $salary, array $originalValues, ?int $changedBy = null, ?string $reason = null): self
    {
        return self::create([
            'user_id' => $salary->user_id,
            'old_salary_local_currency' => $originalValues['salary_local_currency'] ?? null,
            'new_salary_local_currency' => $salary->salary_local_currency,
            'old_salary_euros' => $originalValues['salary_euros'] ?? null,
            'new_salary_euros' => $salary->salary_euros,
            'old_commission' => $originalValues['commission'] ?? null,
            'new_commission' => $salary->commission,
            'old_displayed_salary' => isset($originalValues['salary_euros'], $originalValues['commission']) 
                ? $originalValues['salary_euros'] + $originalValues['commission'] 
                : null,
            'new_displayed_salary' => $salary->displayed_salary,
            'changed_by' => $changedBy ?? auth()->id(),
            'change_reason' => $reason ?? 'Salary updated',
            'metadata' => [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the current salary for the user.
     */
    public function salary(): HasOne
    {
        return $this->hasOne(Salary::class);
    }

    /**
     * Get all salary history records for the user.
     */
    public function salaryHistory(): HasMany
    {
        return $this->hasMany(SalaryHistory::class);
    }

    /**
     * Get all uploaded documents for the user.
     */
    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(UploadedDocument::class);
    }

    /**
     * Get the salary history records ordered by creation date.
     */
    public function salaryHistoryOrdered(): HasMany
    {
        return $this->salaryHistory()->orderBy('created_at', 'desc');
    }

    /**
     * Get only verified uploaded documents.
     */
    public function verifiedDocuments(): HasMany
    {
        return $this->uploadedDocuments()->where('is_verified', true);
    }

    /**
     * Scope to search users by name or email.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Scope to get users with salary information.
     */
    public function scopeWithSalary($query)
    {
        return $query->with('salary');
    }

    /**
     * Get the user's display name (name or email if name is empty).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->email;
    }

    /**
     * Check if user has a current salary record.
     */
    public function hasCurrentSalary(): bool
    {
        return $this->salary !== null;
    }

    /**
     * Get the user's current displayed salary (salary + commission).
     */
    public function getCurrentDisplayedSalaryAttribute(): ?float
    {
        return $this->salary ? $this->salary->displayed_salary : null;
    }

    /**
     * Check if user has admin privileges.
     */
    public function isAdmin(): bool
    {
        // Check admin emails from config
        $adminEmails = config('app.admin_emails', []);
        
        if (!empty($adminEmails)) {
            return in_array($this->email, $adminEmails);
        }
        
        // Fallback: check if user email contains 'admin'
        return str_contains(strtolower($this->email), 'admin');
    }

    /**
     * Get user role for display purposes.
     */
    public function getRoleAttribute(): string
    {
        return $this->isAdmin() ? 'admin' : 'user';
    }
}

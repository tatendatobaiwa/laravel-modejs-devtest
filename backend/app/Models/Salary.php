<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'salary_local_currency',
        'salary_euros',
        'commission',
        'document_path',
        'notes',
    ];

    protected $casts = [
        'salary_euros' => 'decimal:2',
        'commission' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getDisplayedSalaryAttribute()
    {
        return $this->salary_euros + $this->commission;
    }

    public function getFormattedLocalSalaryAttribute()
    {
        return $this->salary_local_currency;
    }

    public function getFormattedEuroSalaryAttribute()
    {
        return '€' . number_format($this->salary_euros, 2);
    }

    public function getFormattedCommissionAttribute()
    {
        return '€' . number_format($this->commission, 2);
    }

    public function getFormattedDisplayedSalaryAttribute()
    {
        return '€' . number_format($this->displayed_salary, 2);
    }
}

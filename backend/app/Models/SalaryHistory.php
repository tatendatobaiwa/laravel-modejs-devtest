<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'old_salary_local_currency',
        'new_salary_local_currency',
        'old_salary_euros',
        'new_salary_euros',
        'old_commission',
        'new_commission',
        'changed_by',
        'change_reason',
    ];

    protected $casts = [
        'old_salary_euros' => 'decimal:2',
        'new_salary_euros' => 'decimal:2',
        'old_commission' => 'decimal:2',
        'new_commission' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

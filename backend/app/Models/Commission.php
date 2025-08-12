<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'is_active',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function getDefaultCommission()
    {
        return self::where('is_active', true)->first() ?? self::create([
            'amount' => 500.00,
            'is_active' => true,
            'description' => 'Default commission rate',
        ]);
    }

    public static function updateCommission($amount)
    {
        $commission = self::getDefaultCommission();
        $commission->update(['amount' => $amount]);
        return $commission;
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Commission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create default commission
        Commission::create([
            'amount' => 500.00,
            'is_active' => true,
            'description' => 'Default commission rate',
        ]);

        // You can add more seeders here
        // $this->call([
        //     UserSeeder::class,
        //     SalarySeeder::class,
        // ]);
    }
}

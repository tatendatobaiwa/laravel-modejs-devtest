<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('salary_local_currency', 12, 2);
            $table->string('local_currency_code', 3)->default('EUR');
            $table->decimal('salary_euros', 12, 2);
            $table->decimal('commission', 10, 2)->default(500.00);
            $table->decimal('displayed_salary', 12, 2)->storedAs('salary_euros + commission');
            $table->date('effective_date')->default(DB::raw('CURRENT_DATE'));
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('user_id');
            $table->index(['user_id', 'effective_date']);
            $table->index(['salary_euros', 'commission']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};

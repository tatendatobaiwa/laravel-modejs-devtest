<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('old_salary_local_currency')->nullable();
            $table->string('new_salary_local_currency')->nullable();
            $table->decimal('old_salary_euros', 10, 2)->nullable();
            $table->decimal('new_salary_euros', 10, 2)->nullable();
            $table->decimal('old_commission', 8, 2)->nullable();
            $table->decimal('new_commission', 8, 2)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('change_reason');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_history');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('salary_id')->constrained()->onDelete('cascade');
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('change_reason');
            $table->string('action')->default('update'); // create, update, delete
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['salary_id', 'changed_at']);
            $table->index('changed_by');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_histories');
    }
};

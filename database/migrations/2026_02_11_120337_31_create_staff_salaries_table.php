<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_salary', 12, 2);
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('account_holder', 100)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->decimal('daily_rate', 12, 2)->nullable();
            $table->decimal('overtime_rate', 12, 2)->nullable();
            $table->json('allowances')->nullable();
            $table->json('deductions')->nullable();
            $table->timestamps();
            
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_salaries');
    }
};
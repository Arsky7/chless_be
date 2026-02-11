<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_salary_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('month');
            $table->integer('year');
            $table->decimal('base_salary', 12, 2);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('allowance_total', 12, 2)->default(0);
            $table->decimal('deduction_total', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_salary', 12, 2);
            $table->date('payment_date')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->string('payment_proof', 255)->nullable();
            $table->text('notes')->nullable();
            $table->json('breakdown')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'month', 'year']);
            $table->index(['payment_status', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_slips');
    }
};
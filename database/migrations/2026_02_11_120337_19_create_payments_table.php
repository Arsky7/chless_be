<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('payment_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['bank_transfer', 'credit_card', 'ewallet', 'cod']);
            $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->json('payment_data')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
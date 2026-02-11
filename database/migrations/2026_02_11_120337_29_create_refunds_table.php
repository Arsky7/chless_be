<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 50)->unique();
            $table->foreignId('return_request_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('shipping_cost_refund', 12, 2)->default(0);
            $table->decimal('tax_refund', 12, 2)->default(0);
            $table->decimal('total_refund', 12, 2);
            $table->enum('method', ['bank_transfer', 'credit', 'ovo', 'gopay', 'shopeepay', 'dana', 'linkaja'])->default('bank_transfer');
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('account_holder', 100)->nullable();
            $table->string('ewallet_phone', 20)->nullable();
            $table->string('transaction_id', 100)->nullable()->comment('ID dari payment gateway');
            $table->json('gateway_response')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            
            $table->index('refund_number');
            $table->index(['status', 'created_at']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            
            // ✅ FIX: TAMBAH nullable() KARENA PAKAI nullOnDelete!
            $table->foreignId('shipping_method_id')
                  ->nullable()                    // ← WAJIB!
                  ->constrained()
                  ->nullOnDelete();
            
            $table->string('courier');
            $table->string('service');
            $table->decimal('cost', 12, 2);
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'failed'])->default('pending');
            $table->date('estimated_delivery')->nullable();
            $table->date('actual_delivery')->nullable();
            $table->json('tracking_history')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('tracking_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
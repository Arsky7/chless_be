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
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_number')->unique();
            $table->string('courier');
            $table->string('service');
            $table->json('shipping_address');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('insurance_cost', 10, 2)->default(0);
            $table->json('package_dimensions')->nullable();
            $table->decimal('package_weight', 8, 2)->nullable();
            $table->enum('status', ['pending', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned'])->default('pending');
            $table->json('tracking_history')->nullable();
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            
            $table->index(['order_id', 'tracking_number']);
            $table->index(['status', 'estimated_delivery']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
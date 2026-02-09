<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('available_quantity')->virtualAs('quantity - reserved_quantity');
            $table->integer('low_stock_threshold')->default(10);
            $table->enum('stock_status', ['in_stock', 'low_stock', 'out_of_stock', 'discontinued'])->default('in_stock');
            $table->timestamp('last_restocked_at')->nullable();
            $table->json('stock_movements')->nullable();
            $table->timestamps();
            
            $table->unique(['product_id', 'warehouse_id']);
            $table->index(['stock_status', 'available_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
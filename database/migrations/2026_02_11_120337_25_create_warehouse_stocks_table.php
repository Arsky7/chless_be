<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_size_id')->constrained('product_sizes')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0)->comment('Di cart, belum checkout');
            $table->integer('available_quantity')->virtualAs('quantity - reserved_quantity');
            $table->string('location_code', 50)->nullable()->comment('Rak A1, B2, dll');
            $table->integer('min_stock')->default(5);
            $table->integer('max_stock')->nullable();
            $table->datetime('last_counted_at')->nullable();
            $table->foreignId('last_counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['warehouse_id', 'product_size_id'], 'warehouse_size_unique');
            $table->index(['warehouse_id', 'product_size_id']);
            $table->index('location_code');
            $table->index(['min_stock', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
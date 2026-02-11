<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
     Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_size_id')->constrained('product_sizes')->restrictOnDelete();
    $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
    $table->integer('quantity');
    $table->decimal('price', 12, 2);
    $table->decimal('cost', 12, 2)->nullable();           // ✅ FIX: hapus '
    $table->decimal('discount_amount', 12, 2)->default(0); // ✅ FIX: hapus '
    $table->integer('shipped_quantity')->default(0);
    $table->integer('returned_quantity')->default(0);
    $table->timestamps();
    
    $table->index(['order_id', 'product_size_id']);
    $table->index('warehouse_id');
});
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

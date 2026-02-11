<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_size_id')->constrained('product_sizes')->cascadeOnDelete();
            
            $table->integer('quantity')->default(1);
            $table->timestamps();
            
            // ✅ BIAR GA DOUBLE ITEM DI CART:
            $table->unique(['cart_id', 'product_size_id'], 'cart_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('size', ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL']);
            $table->string('sku', 50)->unique();
            $table->decimal('price', 12, 2)->nullable()->comment('Null = ikut base_price product');
            $table->decimal('compare_price', 12, 2)->nullable()->comment('Harga coret');
            $table->decimal('cost', 12, 2)->nullable()->comment('Harga modal');
            $table->integer('stock')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['product_id', 'size'], 'product_size_unique');
            $table->index('sku');
            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sizes');
    }
};

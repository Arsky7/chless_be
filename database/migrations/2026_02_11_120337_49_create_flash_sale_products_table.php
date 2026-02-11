<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sale_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_size_id')->constrained('product_sizes')->cascadeOnDelete();
            $table->decimal('special_price', 12, 2);
            $table->integer('max_quantity_per_user')->default(1);
            $table->integer('total_quota')->nullable();
            $table->integer('sold_quantity')->default(0);
            $table->enum('status', ['active', 'paused', 'sold_out', 'ended'])->default('active');
            $table->timestamps();
            
            $table->unique(['flash_sale_id', 'product_size_id'], 'flash_sale_product_unique');
            $table->index(['flash_sale_id', 'status']);
            $table->index('product_size_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_products');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique()->nullable();
            $table->string('model_number')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->enum('price_type', ['fixed', 'variant'])->default('fixed');
            $table->integer('stock_quantity')->default(0);
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'pre_order', 'limited'])->default('in_stock');
            $table->enum('gender', ['men', 'women', 'unisex', 'kids'])->default('unisex');
            $table->json('sizes')->nullable();
            $table->json('colors')->nullable();
            $table->json('attributes')->nullable();
            $table->string('material')->nullable();
            $table->string('care_instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(true);
            $table->boolean('is_best_seller')->default(false);
            $table->boolean('is_sale')->default(false);
            $table->integer('view_count')->default(0);
            $table->integer('sold_count')->default(0);
            $table->integer('rating_avg')->default(0);
            $table->integer('rating_count')->default(0);
            $table->json('metadata')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['slug', 'is_active']);
            $table->index(['category_id', 'brand_id']);
            $table->index(['price', 'sale_price']);
            $table->index(['is_featured', 'is_new', 'is_best_seller']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
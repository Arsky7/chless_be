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
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            
            // ✅ HARGA - HAPUS "AFTER"!
            $table->decimal('base_price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            
            // ✅ STATUS
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'pre_order'])->default('in_stock');
            $table->enum('gender', ['men', 'women', 'unisex'])->default('unisex');
            
            // ✅ WARNA - HAPUS "AFTER"!
            $table->string('color_name', 50);
            $table->string('color_hex', 7);
            
            // ✅ DETAIL FASHION
            $table->string('material', 100)->nullable();
            $table->text('care_instructions')->nullable();
            $table->decimal('weight', 10, 2)->default(0);
            
            // ✅ ATTRIBUTES
            $table->json('attributes')->nullable();
            
            // ✅ SEO
            $table->string('meta_title', 100)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 255)->nullable();
            
            // ✅ STATUS
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('view_count')->default(0);
            $table->integer('sold_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // ✅ INDEXES
            $table->index('slug');
            $table->index('sku');
            $table->index(['is_active', 'is_featured']);
            $table->index(['brand_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
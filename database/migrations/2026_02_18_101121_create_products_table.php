<?php
// database/migrations/2024_01_01_000002_create_products_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            
            // ========================================
            // BASIC INFO - Sesuai form React
            // ========================================
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->onDelete('restrict');
            $table->string('short_description', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('tags', 255)->nullable();
            
            // ========================================
            // PRICING - Sesuai form React
            // ========================================
            $table->decimal('base_price', 15, 2);
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            
            // ========================================
            // ATTRIBUTES - Sesuai form React
            // ========================================
            $table->string('sku', 100)->unique()->nullable();
            $table->enum('gender', ['men', 'women', 'unisex'])->nullable();
            $table->string('material', 255)->nullable();
            $table->string('care_instructions', 255)->nullable();
            
            // ========================================
            // DIMENSIONS & WEIGHT - Sesuai form React
            // ========================================
            $table->decimal('weight', 10, 2)->nullable()->comment('in grams');
            $table->decimal('length', 10, 2)->nullable()->comment('in cm');
            $table->decimal('width', 10, 2)->nullable()->comment('in cm');
            $table->decimal('height', 10, 2)->nullable()->comment('in cm');
            
            // ========================================
            // OPTIONS - Sesuai form React (checkbox)
            // ========================================
            $table->boolean('is_featured')->default(false);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_backorders')->default(false);
            $table->boolean('is_returnable')->default(true);
            
            // ========================================
            // SEO - Sesuai form React
            // ========================================
            $table->string('meta_title', 200)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 255)->nullable();
            
            // ========================================
            // STATUS & VISIBILITY - Sesuai form React
            // ========================================
            $table->boolean('is_active')->default(true);
            $table->enum('visibility', ['public', 'hidden', 'private'])->default('public');
            
            // ========================================
            // STATS - Untuk tracking
            // ========================================
            $table->integer('views_count')->default(0);
            $table->integer('sales_count')->default(0);
            
            // ========================================
            // TIMESTAMPS
            // ========================================
            $table->timestamps();
            $table->softDeletes(); // Untuk menghapus sementara (arsip)
            
            // ========================================
            // INDEXES - Untuk performa query
            // ========================================
            $table->index('category_id');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index('visibility');
            $table->index('created_at');
            $table->index('sku');
            $table->index(['sale_starts_at', 'sale_ends_at']);
            $table->index('gender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
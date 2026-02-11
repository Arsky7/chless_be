<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('banner', 255)->nullable();
            $table->datetime('start_time');
            $table->datetime('end_time');
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->enum('status', ['draft', 'scheduled', 'active', 'ended', 'cancelled'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['start_time', 'end_time']);
            $table->index('status');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_products');
        Schema::dropIfExists('flash_sales');
    }
};

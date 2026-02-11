<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number', 50)->unique();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_size_id')->constrained('product_sizes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['in', 'out', 'transfer', 'adjustment', 'return', 'damaged']);
            $table->integer('quantity');
            $table->integer('before_quantity');
            $table->integer('after_quantity');
            $table->string('reference_type', 50)->comment('App\Models\Order, App\Models\Purchase, etc');
            $table->bigInteger('reference_id')->nullable();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['reference_type', 'reference_id']);
            $table->index(['warehouse_id', 'product_size_id']);
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

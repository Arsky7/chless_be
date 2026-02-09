<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('courier');
            $table->text('description')->nullable();
            $table->enum('type', ['regular', 'express', 'same_day', 'instant'])->default('regular');
            $table->decimal('base_cost', 10, 2)->default(0);
            $table->json('cost_rules')->nullable();
            $table->integer('estimated_days_min')->nullable();
            $table->integer('estimated_days_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_cod_available')->default(false);
            $table->boolean('is_insurance_available')->default(false);
            $table->decimal('insurance_rate', 5, 2)->nullable();
            $table->json('zones')->nullable();
            $table->json('limitations')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
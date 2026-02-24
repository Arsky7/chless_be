<?php
// database/migrations/[timestamp]_create_product_sizes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('size');
            $table->integer('stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->integer('available_stock')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'size']);
            $table->index('product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_sizes');
    }
};
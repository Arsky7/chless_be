<?php
// database/migrations/2024_01_01_000003_create_product_images_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('url');
            $table->string('path');
            $table->string('filename');
            $table->integer('size')->nullable();
            $table->string('mime_type')->nullable();
            $table->boolean('is_main')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Index for main image lookup
            $table->index(['product_id', 'is_main']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_images');
    }
};
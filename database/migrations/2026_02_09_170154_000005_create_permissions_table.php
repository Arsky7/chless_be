<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('guard_name')->default('web');
            $table->string('group')->nullable(); // user, product, order, etc
            $table->string('description')->nullable();
            $table->timestamps();
            
            $table->index(['group', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
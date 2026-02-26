<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->default('Rumah');
            $table->string('receiver_name');
            $table->string('receiver_phone', 20);
            $table->string('province');
            $table->string('province_code', 20)->nullable();
            $table->string('city');
            $table->string('city_code', 20)->nullable();
            $table->string('district');
            $table->string('district_code', 20)->nullable();
            $table->string('village')->nullable();
            $table->string('postal_code', 10);
            $table->text('full_address');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};

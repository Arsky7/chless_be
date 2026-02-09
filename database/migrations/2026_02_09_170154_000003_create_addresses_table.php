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
            $table->string('label'); // Rumah, Kantor, Kos
            $table->string('recipient_name');
            $table->string('phone');
            $table->string('province');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('subdistrict')->nullable();
            $table->text('street_address');
            $table->string('postal_code', 10);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->json('coordinates')->nullable(); // {lat, lng}
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
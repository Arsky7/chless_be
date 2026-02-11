<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('level'); // info, warning, error, debug
            $table->string('message');
            $table->text('context')->nullable();
            $table->string('channel')->default('application');
            $table->json('extra')->nullable();
            $table->timestamps();
            
            $table->index(['level', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
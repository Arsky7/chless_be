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
            $table->string('level'); // info, warning, error, critical, debug
            $table->string('message');
            $table->text('context')->nullable();
            $table->string('channel')->default('application');
            $table->json('extra')->nullable();
            $table->string('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['level', 'created_at']);
            $table->index(['channel', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->foreignId('category_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->integer('views')->default(0);
            $table->boolean('is_published')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            
            $table->index('slug');
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base');
        Schema::dropIfExists('knowledge_categories');
    }
};
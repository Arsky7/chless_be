<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('type', ['packing', 'shipping', 'customer_service', 'stock_opname', 'quality_control', 'cleaning', 'other'])->default('other');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->datetime('deadline')->nullable();
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled', 'failed'])->default('pending');
            $table->string('completion_photo', 255)->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            
            $table->index(['assigned_to', 'status']);
            $table->index(['deadline', 'status']);
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_tasks');
    }
};
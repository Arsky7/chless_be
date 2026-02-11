<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->datetime('check_in')->nullable();
            $table->datetime('check_out')->nullable();
            $table->string('check_in_photo', 255)->nullable();
            $table->string('check_out_photo', 255)->nullable();
            $table->decimal('check_in_lat', 10, 8)->nullable();
            $table->decimal('check_in_lng', 11, 8)->nullable();
            $table->enum('status', ['present', 'late', 'absent', 'leave'])->default('present');
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('day_of_week')->comment('0=Minggu, 1=Senin, ... 6=Sabtu');
            $table->time('start_time');
            $table->time('end_time');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->boolean('is_weekend')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->date('effective_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'day_of_week', 'effective_date']);
            $table->index(['effective_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_schedules');
    }
};
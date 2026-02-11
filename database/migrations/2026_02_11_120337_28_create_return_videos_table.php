<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('returns')->cascadeOnDelete();
            $table->string('video_path', 255);
            $table->string('thumbnail_path', 255)->nullable();
            $table->integer('duration')->nullable()->comment('Durasi dalam detik');
            $table->integer('size')->nullable()->comment('Ukuran dalam bytes');
            $table->string('mime_type', 50)->nullable();
            $table->json('metadata')->nullable()->comment('Timestamp, device info, lokasi');
            $table->string('watermark_text', 100)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();
            
            $table->index(['return_request_id', 'is_verified']);
            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_videos');
    }
};

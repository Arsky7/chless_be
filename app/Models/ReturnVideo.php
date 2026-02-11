<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_request_id',
        'video_path',
        'thumbnail_path',
        'duration',
        'size',
        'mime_type',
        'metadata',
        'watermark_text',
        'is_verified',
        'verified_by',
        'verified_at',
        'verification_notes'
    ];

    protected $casts = [
        'duration' => 'integer',
        'size' => 'integer',
        'metadata' => 'array',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    protected $appends = [
        'video_url',
        'thumbnail_url'
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getVideoUrlAttribute()
    {
        return $this->video_path ? asset('storage/' . $this->video_path) : null;
    }

    public function getThumbnailUrlAttribute()
    {
        return $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path) : null;
    }

    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) {
            return '-';
        }
        
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getFormattedSizeAttribute()
    {
        if (!$this->size) {
            return '-';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }
}

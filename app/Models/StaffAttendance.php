<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'check_in',
        'check_out',
        'check_in_photo',
        'check_out_photo',
        'check_in_lat',
        'check_in_lng',
        'status',
        'overtime_hours',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'check_in_lat' => 'decimal:8',
        'check_in_lng' => 'decimal:8',
        'overtime_hours' => 'decimal:2',
    ];

    protected $appends = [
        'duration',
        'is_late'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDurationAttribute()
    {
        if ($this->check_in && $this->check_out) {
            return $this->check_out->diffInHours($this->check_in);
        }
        return 0;
    }

    public function getIsLateAttribute()
    {
        return $this->status === 'late';
    }

    public function getStatusBadgeAttribute()
    {
        return [
            'present' => 'badge-success',
            'late' => 'badge-warning',
            'absent' => 'badge-danger',
            'leave' => 'badge-info'
        ][$this->status] ?? 'badge-secondary';
    }
}

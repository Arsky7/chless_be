<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'reason',
        'attachment',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'total_days'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTotalDaysAttribute()
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getStatusBadgeAttribute()
    {
        return [
            'pending' => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            'cancelled' => 'badge-secondary'
        ][$this->status] ?? 'badge-secondary';
    }

    public function getTypeBadgeAttribute()
    {
        return [
            'annual' => 'badge-primary',
            'sick' => 'badge-danger',
            'personal' => 'badge-warning',
            'unpaid' => 'badge-secondary',
            'maternity' => 'badge-success',
            'paternity' => 'badge-info',
            'marriage' => 'badge-success',
            'bereavement' => 'badge-dark'
        ][$this->type] ?? 'badge-secondary';
    }
}

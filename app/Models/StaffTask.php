<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'assigned_by',
        'assigned_to',
        'title',
        'description',
        'type',
        'priority',
        'deadline',
        'started_at',
        'completed_at',
        'status',
        'completion_photo',
        'notes',
        'rejection_reason'
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'is_overdue'
    ];

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function getIsOverdueAttribute()
    {
        return $this->deadline && $this->deadline->isPast() && $this->status !== 'completed';
    }

    public function getPriorityBadgeAttribute()
    {
        return [
            'low' => 'badge-info',
            'medium' => 'badge-primary',
            'high' => 'badge-warning',
            'urgent' => 'badge-danger'
        ][$this->priority] ?? 'badge-secondary';
    }

    public function getStatusBadgeAttribute()
    {
        return [
            'pending' => 'badge-secondary',
            'in_progress' => 'badge-primary',
            'completed' => 'badge-success',
            'cancelled' => 'badge-danger',
            'failed' => 'badge-dark'
        ][$this->status] ?? 'badge-secondary';
    }

    public function getTypeBadgeAttribute()
    {
        return [
            'packing' => 'badge-info',
            'shipping' => 'badge-primary',
            'customer_service' => 'badge-success',
            'stock_opname' => 'badge-warning',
            'quality_control' => 'badge-danger',
            'cleaning' => 'badge-secondary',
            'other' => 'badge-dark'
        ][$this->type] ?? 'badge-secondary';
    }
}

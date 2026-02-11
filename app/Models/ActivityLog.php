<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'log_id',
        'user_id',
        'user_type',
        'log_type',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'changes',
        'description',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changes' => 'array',
    ];

    protected $appends = [
        'user_name',
        'formatted_created_at'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model()
    {
        return $this->morphTo();
    }

    public function getUserNameAttribute()
    {
        return $this->user ? $this->user->name : 'System';
    }

    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('d M Y H:i:s');
    }

    public function getLogTypeBadgeAttribute()
    {
        return [
            'login' => 'badge-success',
            'order' => 'badge-primary',
            'payment' => 'badge-info',
            'stock' => 'badge-warning',
            'user' => 'badge-secondary',
            'product' => 'badge-danger'
        ][$this->log_type] ?? 'badge-secondary';
    }

    public function getActionBadgeAttribute()
    {
        return [
            'create' => 'badge-success',
            'update' => 'badge-warning',
            'delete' => 'badge-danger',
            'view' => 'badge-info',
            'approve' => 'badge-success',
            'reject' => 'badge-danger'
        ][$this->action] ?? 'badge-secondary';
    }
}

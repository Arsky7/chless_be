<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnRequest extends Model
{
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'order_id',
        'user_id',
        'order_item_id',
        'product_size_id',
        'reason',
        'description',
        'quantity',
        'refund_amount',
        'refund_method',
        'video_evidence',
        'video_verified',
        'video_verified_by',
        'video_verified_at',
        'photo_1',
        'photo_2',
        'photo_3',
        'status',
        'admin_notes',
        'customer_notes',
        'processed_at'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'refund_amount' => 'decimal:2',
        'video_verified' => 'boolean',
        'video_verified_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected $appends = [
        'refund_method_label'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ReturnVideo::class, 'return_request_id');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class, 'return_request_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'video_verified_by');
    }

    public function getRefundMethodLabelAttribute()
    {
        return [
            'bank_transfer' => 'Transfer Bank',
            'wallet' => 'Dompet Digital',
            'original_payment' => 'Pembayaran Asli'
        ][$this->refund_method] ?? $this->refund_method;
    }

    public function getStatusBadgeAttribute()
    {
        return [
            'pending' => 'badge-warning',
            'under_review' => 'badge-info',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            'pickup_scheduled' => 'badge-primary',
            'item_received' => 'badge-primary',
            'quality_check' => 'badge-info',
            'refund_processed' => 'badge-success',
            'completed' => 'badge-success',
            'cancelled' => 'badge-secondary'
        ][$this->status] ?? 'badge-secondary';
    }
}

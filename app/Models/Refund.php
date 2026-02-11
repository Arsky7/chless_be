<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_number',
        'return_request_id',
        'order_id',
        'payment_id',
        'user_id',
        'amount',
        'shipping_cost_refund',
        'tax_refund',
        'total_refund',
        'method',
        'bank_name',
        'bank_account',
        'account_holder',
        'ewallet_phone',
        'transaction_id',
        'gateway_response',
        'status',
        'processed_by',
        'processed_at',
        'notes',
        'failure_reason'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'shipping_cost_refund' => 'decimal:2',
        'tax_refund' => 'decimal:2',
        'total_refund' => 'decimal:2',
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
    ];

    protected $appends = [
        'formatted_amount',
        'method_label'
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getFormattedAmountAttribute()
    {
        return 'Rp ' . number_format($this->total_refund, 0, ',', '.');
    }

    public function getMethodLabelAttribute()
    {
        return [
            'bank_transfer' => 'Transfer Bank',
            'credit' => 'Kredit',
            'ovo' => 'OVO',
            'gopay' => 'GoPay',
            'shopeepay' => 'ShopeePay',
            'dana' => 'DANA',
            'linkaja' => 'LinkAja'
        ][$this->method] ?? $this->method;
    }

    public function getStatusBadgeAttribute()
    {
        return [
            'pending' => 'badge-warning',
            'processing' => 'badge-info',
            'completed' => 'badge-success',
            'failed' => 'badge-danger',
            'cancelled' => 'badge-secondary'
        ][$this->status] ?? 'badge-secondary';
    }
}

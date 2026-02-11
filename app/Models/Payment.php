<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_number',
        'amount',
        'paid_amount',
        'currency',
        'method',
        'gateway',
        'transaction_id',
        'status',
        'payment_data',
        'notes',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'payment_data' => 'array',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    // BOOT
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = 'PAY-' . date('Ymd') . '-' . strtoupper(uniqid());
            }
            
            if (empty($payment->expired_at)) {
                $payment->expired_at = now()->addHours(24);
            }
        });
    }

    // RELATIONSHIPS
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // SCOPES
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('method', $method);
    }

    public function scopeByGateway($query, $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ACCESSORS
    public function getFormattedAmountAttribute()
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    public function getFormattedPaidAmountAttribute()
    {
        return 'Rp ' . number_format($this->paid_amount, 0, ',', '.');
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Menunggu Pembayaran',
            'paid' => 'Sudah Dibayar',
            'failed' => 'Gagal',
            'expired' => 'Kadaluarsa',
            'refunded' => 'Dikembalikan',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    public function getMethodLabelAttribute()
    {
        $labels = [
            'bank_transfer' => 'Transfer Bank',
            'credit_card' => 'Kartu Kredit',
            'ewallet' => 'E-Wallet',
            'cod' => 'Bayar di Tempat',
            'virtual_account' => 'Virtual Account',
        ];
        
        return $labels[$this->method] ?? $this->method;
    }

    public function getIsPendingAttribute()
    {
        return $this->status === 'pending';
    }

    public function getIsPaidAttribute()
    {
        return $this->status === 'paid';
    }

    public function getIsExpiredAttribute()
    {
        return $this->status === 'expired' || 
               ($this->expired_at && $this->expired_at < now() && $this->status === 'pending');
    }

    public function getIsRefundableAttribute()
    {
        return $this->status === 'paid' && 
               $this->paid_at && 
               $this->paid_at->diffInDays(now()) <= 30;
    }

    // HELPERS
    public function markAsPaid($transactionId = null, $paymentData = null)
    {
        $this->update([
            'status' => 'paid',
            'paid_amount' => $this->amount,
            'paid_at' => now(),
            'transaction_id' => $transactionId,
            'payment_data' => $paymentData,
        ]);
        
        // Update order payment status
        $this->order->markAsPaid();
        
        return $this;
    }

    public function markAsFailed($reason = null)
    {
        $this->update([
            'status' => 'failed',
            'notes' => $this->notes . "\nFailed: " . $reason,
        ]);
        
        return $this;
    }

    public function markAsExpired()
    {
        $this->update([
            'status' => 'expired',
        ]);
        
        return $this;
    }

    public function refund($amount = null, $reason = null)
    {
        $refundAmount = $amount ?? $this->paid_amount;
        
        if ($refundAmount > $this->paid_amount) {
            throw new \Exception('Refund amount cannot exceed paid amount');
        }
        
        $this->update([
            'status' => $refundAmount < $this->paid_amount ? 'partially_refunded' : 'refunded',
            'notes' => $this->notes . "\nRefunded: " . $refundAmount . " - Reason: " . $reason,
        ]);
        
        // Update order payment status
        $this->order->update([
            'payment_status' => $refundAmount < $this->paid_amount ? 'partially_refunded' : 'refunded',
        ]);
        
        return $this;
    }

    public function generatePaymentLink()
    {
        // Implementation depends on payment gateway
        // Example for Midtrans/Xendit
        switch ($this->gateway) {
            case 'midtrans':
                return $this->generateMidtransPaymentLink();
            case 'xendit':
                return $this->generateXenditPaymentLink();
            default:
                return null;
        }
    }

    public function checkPaymentStatus()
    {
        // Check with payment gateway
        // This would call the gateway API
        return $this->status;
    }

    public function getPaymentDataValue($key, $default = null)
    {
        return $this->payment_data[$key] ?? $default;
    }

    public static function getStats($period = 'month')
    {
        $query = self::query();
        
        if ($period === 'month') {
            $query->whereMonth('created_at', now()->month);
        } elseif ($period === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($period === 'day') {
            $query->whereDate('created_at', now());
        }
        
        return [
            'total' => $query->count(),
            'pending' => $query->clone()->pending()->count(),
            'paid' => $query->clone()->paid()->count(),
            'failed' => $query->clone()->failed()->count(),
            'expired' => $query->clone()->expired()->count(),
            'refunded' => $query->clone()->refunded()->count(),
            'total_amount' => $query->clone()->sum('amount'),
            'total_paid' => $query->clone()->where('status', 'paid')->sum('paid_amount'),
        ];
    }

    private function generateMidtransPaymentLink()
    {
        // Midtrans implementation
        return 'https://app.midtrans.com/snap/v2/vtweb/' . $this->transaction_id;
    }

    private function generateXenditPaymentLink()
    {
        // Xendit implementation
        return $this->getPaymentDataValue('invoice_url');
    }
}
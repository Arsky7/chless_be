<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'user_id',
        'address_id',
        'subtotal',
        'shipping_cost',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'status',
        'payment_status',
        'payment_method',
        'payment_gateway',
        'shipping_method',
        'tracking_number',
        'customer_notes',
        'admin_notes',
        'metadata',
        'confirmed_at',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
        'confirmed_at' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // BOOT
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());
            }
        });
    }

    // RELATIONSHIPS
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }

    // SCOPES
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ACCESSORS
    public function getFormattedSubtotalAttribute()
    {
        return 'Rp ' . number_format($this->subtotal, 0, ',', '.');
    }

    public function getFormattedShippingCostAttribute()
    {
        return 'Rp ' . number_format($this->shipping_cost, 0, ',', '.');
    }

    public function getFormattedTaxAmountAttribute()
    {
        return 'Rp ' . number_format($this->tax_amount, 0, ',', '.');
    }

    public function getFormattedDiscountAmountAttribute()
    {
        return 'Rp ' . number_format($this->discount_amount, 0, ',', '.');
    }

    public function getFormattedTotalAmountAttribute()
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Menunggu Pembayaran',
            'confirmed' => 'Dikonfirmasi',
            'processing' => 'Diproses',
            'shipped' => 'Dikirim',
            'delivered' => 'Sampai',
            'cancelled' => 'Dibatalkan',
            'returned' => 'Dikembalikan',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    public function getPaymentStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Menunggu',
            'paid' => 'Lunas',
            'failed' => 'Gagal',
            'refunded' => 'Dikembalikan',
            'partially_refunded' => 'Sebagian Dikembalikan',
        ];
        
        return $labels[$this->payment_status] ?? $this->payment_status;
    }

    public function getIsPaidAttribute()
    {
        return $this->payment_status === 'paid';
    }

    public function getIsPendingAttribute()
    {
        return $this->status === 'pending';
    }

    public function getIsDeliveredAttribute()
    {
        return $this->status === 'delivered';
    }

    public function getIsCancellableAttribute()
    {
        return in_array($this->status, ['pending', 'confirmed', 'processing']);
    }

    // HELPERS
    public function addItem($productId, $quantity, $unitPrice, $options = [])
    {
        $product = Product::findOrFail($productId);
        
        return OrderItem::create([
            'order_id' => $this->id,
            'product_id' => $productId,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'options' => $options,
        ]);
    }

    public function calculateTotals()
    {
        $subtotal = $this->items->sum('total_price');
        $this->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal + $this->shipping_cost + $this->tax_amount - $this->discount_amount,
        ]);
        
        return $this;
    }

    public function confirm()
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        
        return $this;
    }

    public function markAsPaid()
    {
        $this->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
        
        return $this;
    }

    public function markAsShipped($trackingNumber = null)
    {
        $this->update([
            'status' => 'shipped',
            'shipped_at' => now(),
            'tracking_number' => $trackingNumber,
        ]);
        
        return $this;
    }

    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        
        return $this;
    }

    public function cancel($reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'admin_notes' => $this->admin_notes . "\nCancelled: " . $reason,
        ]);
        
        // Restock items
        foreach ($this->items as $item) {
            $item->product->updateStock($item->quantity, 'increment');
        }
        
        return $this;
    }

    public function applyDiscount($discountCode)
    {
        $discount = Discount::where('code', $discountCode)
                            ->where('is_active', true)
                            ->where('start_date', '<=', now())
                            ->where('end_date', '>=', now())
                            ->first();
        
        if ($discount) {
            $this->update([
                'discount_amount' => $discount->calculateDiscount($this->subtotal),
                'metadata' => array_merge($this->metadata ?? [], ['discount_applied' => $discount->id]),
            ]);
            
            $this->calculateTotals();
            
            return $discount;
        }
        
        return null;
    }

    public function generateInvoice()
    {
        $invoice = Invoice::create([
            'order_id' => $this->id,
            'invoice_number' => 'INV-' . date('Ymd') . '-' . strtoupper(uniqid()),
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
            'amount' => $this->total_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'status' => $this->is_paid ? 'paid' : 'sent',
            'billing_info' => $this->address->toArray(),
            'items' => $this->items->toArray(),
        ]);
        
        return $invoice;
    }

    public function getItemsTotalQuantity()
    {
        return $this->items->sum('quantity');
    }

    public static function getStats($userId = null)
    {
        $query = $userId ? self::where('user_id', $userId) : self::query();
        
        return [
            'total' => $query->count(),
            'pending' => $query->clone()->pending()->count(),
            'processing' => $query->clone()->processing()->count(),
            'shipped' => $query->clone()->shipped()->count(),
            'delivered' => $query->clone()->delivered()->count(),
            'cancelled' => $query->clone()->cancelled()->count(),
            'total_amount' => $query->clone()->sum('total_amount'),
        ];
    }
}
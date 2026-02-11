<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'amount',
        'tax_amount',
        'total_amount',
        'status',
        'notes',
        'billing_info',
        'items',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'billing_info' => 'array',
        'items' => 'array',
    ];

    // BOOT
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());
            }
            
            if (empty($invoice->invoice_date)) {
                $invoice->invoice_date = now();
            }
            
            if (empty($invoice->due_date)) {
                $invoice->due_date = now()->addDays(7);
            }
        });
    }

    // RELATIONSHIPS
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // SCOPES
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
                     ->orWhere(function($q) {
                         $q->where('status', 'sent')
                           ->where('due_date', '<', now());
                     });
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeByOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
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

    public function getFormattedTaxAmountAttribute()
    {
        return 'Rp ' . number_format($this->tax_amount, 0, ',', '.');
    }

    public function getFormattedTotalAmountAttribute()
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'draft' => 'Draft',
            'sent' => 'Terkirim',
            'paid' => 'Lunas',
            'overdue' => 'Jatuh Tempo',
            'cancelled' => 'Dibatalkan',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    public function getIsOverdueAttribute()
    {
        return $this->status === 'overdue' || 
               ($this->status === 'sent' && $this->due_date < now());
    }

    public function getIsPaidAttribute()
    {
        return $this->status === 'paid';
    }

    public function getIsDraftAttribute()
    {
        return $this->status === 'draft';
    }

    public function getDaysUntilDueAttribute()
    {
        if ($this->is_paid || $this->is_overdue) {
            return 0;
        }
        
        return now()->diffInDays($this->due_date, false);
    }

    // HELPERS
    public function markAsSent()
    {
        $this->update(['status' => 'sent']);
        return $this;
    }

    public function markAsPaid()
    {
        $this->update(['status' => 'paid']);
        return $this;
    }

    public function markAsOverdue()
    {
        $this->update(['status' => 'overdue']);
        return $this;
    }

    public function markAsCancelled()
    {
        $this->update(['status' => 'cancelled']);
        return $this;
    }

    public function sendToCustomer()
    {
        // Send email to customer
        // Implementation depends on your email setup
        
        $this->markAsSent();
        
        return $this;
    }

    public function generatePDF()
    {
        // Generate PDF invoice
        // You can use DomPDF or similar package
        
        return storage_path('app/invoices/' . $this->invoice_number . '.pdf');
    }

    public function getDownloadUrl()
    {
        return route('invoices.download', $this->id);
    }

    public function getPreviewUrl()
    {
        return route('invoices.preview', $this->id);
    }

    public function updateFromOrder()
    {
        if (!$this->order) {
            return $this;
        }
        
        $this->update([
            'amount' => $this->order->subtotal,
            'tax_amount' => $this->order->tax_amount,
            'total_amount' => $this->order->total_amount,
            'billing_info' => $this->order->address->toArray(),
            'items' => $this->order->items->map(function($item) {
                return [
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'options' => $item->options,
                ];
            })->toArray(),
        ]);
        
        return $this;
    }

    public static function generateFromOrder($order)
    {
        $invoice = self::create([
            'order_id' => $order->id,
            'amount' => $order->subtotal,
            'tax_amount' => $order->tax_amount,
            'total_amount' => $order->total_amount,
            'status' => $order->is_paid ? 'paid' : 'sent',
            'billing_info' => $order->address->toArray(),
            'items' => $order->items->map(function($item) {
                return [
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'options' => $item->options,
                ];
            })->toArray(),
        ]);
        
        return $invoice;
    }

    public function getBillingAddress()
    {
        $info = $this->billing_info ?? [];
        
        $parts = [
            $info['recipient_name'] ?? '',
            $info['phone'] ?? '',
            $info['street_address'] ?? '',
            $info['subdistrict'] ?? '',
            $info['district'] ?? '',
            $info['city'] ?? '',
            $info['province'] ?? '',
            $info['postal_code'] ?? '',
        ];
        
        return implode("\n", array_filter($parts));
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'start_date',
        'end_date',
        'is_active',
        'applicable_to',
        'applicable_ids',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'applicable_ids' => 'array',
    ];

    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where('start_date', '<=', now())
                     ->where('end_date', '>=', now());
    }

    public function scopeValid($query)
    {
        return $query->active()->where(function($q) {
            $q->whereNull('max_uses')
              ->orWhereRaw('used_count < max_uses');
        });
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeApplicableToAll($query)
    {
        return $query->where('applicable_to', 'all');
    }

    public function scopeApplicableToProducts($query)
    {
        return $query->where('applicable_to', 'products');
    }

    public function scopeApplicableToCategories($query)
    {
        return $query->where('applicable_to', 'categories');
    }

    public function scopeApplicableToCollections($query)
    {
        return $query->where('applicable_to', 'collections');
    }

    public function scopeCurrent($query)
    {
        return $query->where(function($q) {
            $q->whereNull('start_date')
              ->orWhere('start_date', '<=', now());
        })->where(function($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now());
        });
    }

    // ACCESSORS
    public function getFormattedValueAttribute()
    {
        if ($this->type === 'percentage') {
            return $this->value . '%';
        }
        
        return 'Rp ' . number_format($this->value, 0, ',', '.');
    }

    public function getFormattedMinOrderAmountAttribute()
    {
        if (!$this->min_order_amount) {
            return '-';
        }
        
        return 'Rp ' . number_format($this->min_order_amount, 0, ',', '.');
    }

    public function getUsagePercentageAttribute()
    {
        if (!$this->max_uses) {
            return 0;
        }
        
        return round(($this->used_count / $this->max_uses) * 100);
    }

    public function getIsUnlimitedAttribute()
    {
        return !$this->max_uses;
    }

    public function getIsExpiredAttribute()
    {
        return $this->end_date && $this->end_date < now();
    }

    public function getIsStartedAttribute()
    {
        return !$this->start_date || $this->start_date <= now();
    }

    public function getIsValidAttribute()
    {
        return $this->is_active && 
               $this->is_started && 
               !$this->is_expired &&
               ($this->is_unlimited || $this->used_count < $this->max_uses);
    }

    public function getApplicableToLabelAttribute()
    {
        $labels = [
            'all' => 'Semua Produk',
            'categories' => 'Kategori Tertentu',
            'products' => 'Produk Tertentu',
            'collections' => 'Koleksi Tertentu',
        ];
        
        return $labels[$this->applicable_to] ?? $this->applicable_to;
    }

    // HELPERS
    public function calculateDiscount($amount)
    {
        if (!$this->is_valid) {
            return 0;
        }
        
        if ($this->min_order_amount && $amount < $this->min_order_amount) {
            return 0;
        }
        
        switch ($this->type) {
            case 'percentage':
                return $amount * ($this->value / 100);
            case 'fixed_amount':
                return min($this->value, $amount);
            case 'free_shipping':
                // This would be handled differently in shipping calculation
                return 0;
            default:
                return 0;
        }
    }

    public function incrementUsage()
    {
        $this->increment('used_count');
        return $this;
    }

    public function decrementUsage()
    {
        if ($this->used_count > 0) {
            $this->decrement('used_count');
        }
        return $this;
    }

    public function isApplicableToProduct($productId)
    {
        if ($this->applicable_to === 'all') {
            return true;
        }
        
        if ($this->applicable_to === 'products') {
            $applicableIds = $this->applicable_ids ?? [];
            return in_array($productId, $applicableIds);
        }
        
        if ($this->applicable_to === 'categories') {
            $product = Product::find($productId);
            if (!$product) {
                return false;
            }
            
            $applicableIds = $this->applicable_ids ?? [];
            return in_array($product->category_id, $applicableIds);
        }
        
        if ($this->applicable_to === 'collections') {
            $product = Product::find($productId);
            if (!$product) {
                return false;
            }
            
            $applicableIds = $this->applicable_ids ?? [];
            $productCollections = $product->collections->pluck('id')->toArray();
            
            return !empty(array_intersect($applicableIds, $productCollections));
        }
        
        return false;
    }

    public function isApplicableToOrder($orderAmount, $items = [])
    {
        if (!$this->is_valid) {
            return false;
        }
        
        if ($this->min_order_amount && $orderAmount < $this->min_order_amount) {
            return false;
        }
        
        if ($this->applicable_to === 'all') {
            return true;
        }
        
        // Check if any item is applicable
        foreach ($items as $item) {
            if ($this->isApplicableToProduct($item['product_id'])) {
                return true;
            }
        }
        
        return false;
    }

    public function getApplicableProducts()
    {
        if ($this->applicable_to === 'all') {
            return Product::active()->get();
        }
        
        if ($this->applicable_to === 'products') {
            return Product::whereIn('id', $this->applicable_ids ?? [])->active()->get();
        }
        
        if ($this->applicable_to === 'categories') {
            return Product::whereIn('category_id', $this->applicable_ids ?? [])->active()->get();
        }
        
        if ($this->applicable_to === 'collections') {
            return Product::whereHas('collections', function($q) {
                $q->whereIn('collections.id', $this->applicable_ids ?? []);
            })->active()->get();
        }
        
        return collect();
    }

    public function validateCode($code)
    {
        return $this->code === $code && $this->is_valid;
    }

    public static function validateAndApply($code, $orderAmount, $items = [])
    {
        $discount = self::where('code', $code)->valid()->first();
        
        if (!$discount) {
            return [
                'success' => false,
                'message' => 'Kode diskon tidak valid atau sudah kadaluarsa',
            ];
        }
        
        if (!$discount->isApplicableToOrder($orderAmount, $items)) {
            return [
                'success' => false,
                'message' => 'Diskon tidak berlaku untuk pesanan ini',
            ];
        }
        
        $discountAmount = $discount->calculateDiscount($orderAmount);
        
        return [
            'success' => true,
            'discount' => $discount,
            'amount' => $discountAmount,
            'message' => 'Diskon berhasil diterapkan',
        ];
    }

    public function getDaysRemaining()
    {
        if (!$this->end_date) {
            return null;
        }
        
        return now()->diffInDays($this->end_date, false);
    }
}
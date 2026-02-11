<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSize extends Model
{
    protected $fillable = [
        'product_id',
        'size',
        'sku',
        'price',
        'compare_price',
        'cost',
        'stock',
        'low_stock_threshold',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'final_price',
        'available_stock'
    ];

    // ============ RELATIONSHIPS ============
    
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ============ ACCESSORS ============
    
    public function getFinalPriceAttribute()
    {
        if ($this->price) {
            return $this->price;
        }
        
        if ($this->product && $this->product->is_on_sale) {
            return $this->product->sale_price;
        }
        
        return $this->product->base_price ?? 0;
    }

    public function getAvailableStockAttribute()
    {
        return $this->warehouseStocks()->sum('available_quantity');
    }

    public function getIsLowStockAttribute()
    {
        return $this->available_stock <= $this->low_stock_threshold;
    }

    public function getIsOutOfStockAttribute()
    {
        return $this->available_stock <= 0;
    }

    public function getFormattedPriceAttribute()
    {
        return 'Rp ' . number_format($this->final_price, 0, ',', '.');
    }

    // ============ SCOPES ============
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeBySize($query, $size)
    {
        return $query->where('size', $size);
    }

    // ============ HELPERS ============
    
    public function reserveStock($quantity)
    {
        // Logic untuk reserve stock di warehouse
    }

    public function releaseStock($quantity)
    {
        // Logic untuk release stock
    }

    public function decreaseStock($quantity)
    {
        $this->decrement('stock', $quantity);
    }
}
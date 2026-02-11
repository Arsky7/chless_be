<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_size_id',
        'warehouse_id',
        'quantity',
        'price',
        'cost',
        'discount_amount',
        'shipped_quantity',
        'returned_quantity'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipped_quantity' => 'integer',
        'returned_quantity' => 'integer',
    ];

    protected $appends = [
        'subtotal',
        'product_name',
        'size',
        'sku'
    ];

    // ============ RELATIONSHIPS ============
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // ============ ACCESSORS ============
    
    public function getSubtotalAttribute()
    {
        return $this->price * $this->quantity;
    }

    public function getProductNameAttribute()
    {
        return $this->productSize->product->name;
    }

    public function getSizeAttribute()
    {
        return $this->productSize->size;
    }

    public function getSkuAttribute()
    {
        return $this->productSize->sku;
    }

    public function getColorNameAttribute()
    {
        return $this->productSize->product->color_name;
    }

    public function getColorHexAttribute()
    {
        return $this->productSize->product->color_hex;
    }

    public function getRemainingToShipAttribute()
    {
        return $this->quantity - $this->shipped_quantity;
    }

    public function getRemainingToReturnAttribute()
    {
        return $this->shipped_quantity - $this->returned_quantity;
    }

    // ============ HELPERS ============
    
    public function ship($quantity)
    {
        if ($quantity > $this->remaining_to_ship) {
            throw new \Exception('Cannot ship more than ordered quantity');
        }
        
        $this->increment('shipped_quantity', $quantity);
        
        return $this;
    }

    public function markReturned($quantity)
    {
        if ($quantity > $this->remaining_to_return) {
            throw new \Exception('Cannot return more than shipped quantity');
        }
        
        $this->increment('returned_quantity', $quantity);
        
        return $this;
    }
}
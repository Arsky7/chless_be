<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_size_id',
        'quantity'
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected $appends = [
        'subtotal',
        'formatted_subtotal'
    ];

    // ============ RELATIONSHIPS ============
    
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    // ============ ACCESSORS ============
    
    public function getSubtotalAttribute()
    {
        return $this->productSize->final_price * $this->quantity;
    }

    public function getFormattedSubtotalAttribute()
    {
        return 'Rp ' . number_format($this->subtotal, 0, ',', '.');
    }

    public function getProductAttribute()
    {
        return $this->productSize->product;
    }

    public function getSizeAttribute()
    {
        return $this->productSize->size;
    }

    public function getPriceAttribute()
    {
        return $this->productSize->final_price;
    }

    public function getFormattedPriceAttribute()
    {
        return $this->productSize->formatted_price;
    }
}
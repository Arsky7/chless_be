<?php
// app/Models/ProductSize.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSize extends Model
{
    protected $fillable = [
        'product_id',
        'size',
        'stock',
        'reserved_stock',
        'available_stock'
    ];

    protected $casts = [
        'stock' => 'integer',
        'reserved_stock' => 'integer',
        'available_stock' => 'integer'
    ];

    protected $appends = [
        'is_low_stock',
        'is_out_of_stock'
    ];

    /**
     * Relasi ke Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Cek apakah stok menipis
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->available_stock > 0 && $this->available_stock < 10;
    }

    /**
     * Cek apakah stok habis
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->available_stock <= 0;
    }

    /**
     * Update available stock
     */
    public function updateAvailableStock(): void
    {
        $this->available_stock = $this->stock - $this->reserved_stock;
        $this->save();
    }
}
<?php
// app/Models/ProductImage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'url',
        'path',
        'filename',
        'size',
        'mime_type',
        'is_main',
        'sort_order'
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'size' => 'integer',
        'sort_order' => 'integer'
    ];

    protected $appends = [
        'full_url',
        'thumbnail_url'
    ];

    /**
     * Relasi ke Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get full URL gambar
     */
    public function getFullUrlAttribute(): string
    {
        return $this->url ?? asset('storage/' . $this->path);
    }

    /**
     * Get thumbnail URL (bisa ditambahkan logic untuk resize)
     */
    public function getThumbnailUrlAttribute(): string
    {
        // Implementasi thumbnail bisa ditambahkan nanti
        return $this->full_url;
    }

    /**
     * Set sebagai gambar utama
     */
    public function setAsMain(): void
    {
        // Reset gambar utama lain
        $this->product->images()->update(['is_main' => false]);
        
        // Set gambar ini sebagai utama
        $this->is_main = true;
        $this->save();
    }
}
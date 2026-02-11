<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'thumbnail_path',
        'is_main',
        'sort_order',
        'alt_text',
        'metadata',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'metadata' => 'array',
    ];

    // RELATIONSHIPS
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // ACCESSORS
    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }
        return null;
    }

    public function getThumbnailUrlAttribute()
    {
        if ($this->thumbnail_path) {
            return asset('storage/' . $this->thumbnail_path);
        }
        return $this->image_url;
    }

    // SCOPES
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // HELPERS
    public function setAsMain()
    {
        // Remove main from other images
        $this->product->images()->update(['is_main' => false]);
        
        // Set this as main
        $this->update(['is_main' => true]);
        
        return $this;
    }

    public function generateThumbnail()
    {
        // Implementation for thumbnail generation
        // You can use Intervention Image here
        return $this;
    }
}
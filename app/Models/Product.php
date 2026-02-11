<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'brand_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'base_price',
        'sale_price',
        'sale_starts_at',
        'sale_ends_at',
        'stock_status',
        'gender',
        'color_name',
        'color_hex',
        'material',
        'care_instructions',
        'weight',
        'attributes',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_active',
        'is_featured',
        'view_count',
        'sold_count'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
        'weight' => 'decimal:2',
        'attributes' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'sold_count' => 'integer',
    ];

    protected $appends = [
        'final_price',
        'is_on_sale',
        'main_image_url',
        'stock_quantity'
    ];

    // ============ RELATIONSHIPS ============
    
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sizes()
    {
        return $this->hasMany(ProductSize::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    // ============ SCOPES ============
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_status', 'in_stock');
    }

    public function scopeOnSale($query)
    {
        return $query->whereNotNull('sale_price')
            ->where(function($q) {
                $q->whereNull('sale_starts_at')
                  ->orWhere('sale_starts_at', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('sale_ends_at')
                  ->orWhere('sale_ends_at', '>=', now());
            });
    }

    public function scopeByGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

    public function scopeByColor($query, $colorHex)
    {
        return $query->where('color_hex', $colorHex);
    }

    // ============ ACCESSORS ============
    
    public function getFinalPriceAttribute()
    {
        if ($this->is_on_sale && $this->sale_price) {
            return $this->sale_price;
        }
        return $this->base_price;
    }

    public function getIsOnSaleAttribute()
    {
        if (!$this->sale_price) {
            return false;
        }

        $now = now();
        
        if ($this->sale_starts_at && $this->sale_starts_at > $now) {
            return false;
        }
        
        if ($this->sale_ends_at && $this->sale_ends_at < $now) {
            return false;
        }
        
        return true;
    }

    public function getMainImageUrlAttribute()
    {
        $mainImage = $this->images()->where('is_main', true)->first();
        
        if ($mainImage) {
            return $mainImage->url;
        }
        
        $firstImage = $this->images()->first();
        return $firstImage->url ?? null;
    }

    public function getStockQuantityAttribute()
    {
        return $this->sizes()->sum('stock');
    }

    public function getDiscountPercentageAttribute()
    {
        if (!$this->is_on_sale || !$this->base_price) {
            return 0;
        }
        
        return round((($this->base_price - $this->sale_price) / $this->base_price) * 100);
    }

    public function getFormattedPriceAttribute()
    {
        return 'Rp ' . number_format($this->final_price, 0, ',', '.');
    }

    // ============ HELPERS ============
    
    public function updateSoldCount()
    {
        $soldCount = $this->sizes()
            ->join('order_items', 'product_sizes.id', '=', 'order_items.product_size_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.status', ['delivered', 'completed', 'shipped'])
            ->sum('order_items.quantity');
        
        $this->update(['sold_count' => $soldCount]);
    }

    public function incrementViewCount()
    {
        $this->increment('view_count');
    }

    public function hasSize($size)
    {
        return $this->sizes()->where('size', $size)->exists();
    }

    public function getSize($size)
    {
        return $this->sizes()->where('size', $size)->first();
    }
}
<?php
// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';
    
    protected $primaryKey = 'id';
    
    public $incrementing = true;
    
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'category_id',
        'short_description',
        'description',
        'tags',
        'base_price',
        'sale_price',
        'sale_starts_at',
        'sale_ends_at',
        'gender',
        'material',
        'care_instructions',
        'weight',
        'length',
        'width',
        'height',
        'is_featured',
        'track_inventory',
        'allow_backorders',
        'is_returnable',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_active',
        'visibility',
        'views_count',
        'sales_count'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_featured' => 'boolean',
        'track_inventory' => 'boolean',
        'allow_backorders' => 'boolean',
        'is_returnable' => 'boolean',
        'is_active' => 'boolean',
        'views_count' => 'integer',
        'sales_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $appends = [
        'current_price',
        'is_on_sale',
        'total_stock',
        'stock_status',
        'price_formatted',
        'sale_price_formatted',
        'discount_percentage'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
            
            if (empty($product->sku)) {
                $product->sku = 'PRD-' . strtoupper(Str::random(8));
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    // ======================== RELATIONSHIPS ========================

    /**
     * Relasi ke Category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relasi ke ProductImage
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Relasi ke ProductSize
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    /**
     * Relasi ke OrderItem
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Relasi ke main image (satu gambar utama)
     */
    public function mainImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_main', true);
    }

    /**
     * Relasi ke Order melalui orderItems
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items')
                    ->withPivot('quantity', 'price', 'size')
                    ->withTimestamps();
    }

    // ======================== ACCESSORS & APPENDS ========================

    /**
     * Get current price (sale price jika sedang diskon, base price jika tidak)
     */
    public function getCurrentPriceAttribute()
    {
        if ($this->is_on_sale) {
            return (float) $this->sale_price;
        }
        return (float) $this->base_price;
    }

    /**
     * Cek apakah produk sedang diskon
     */
    public function getIsOnSaleAttribute(): bool
    {
        if (!$this->sale_price || $this->sale_price <= 0) {
            return false;
        }

        if ($this->sale_price >= $this->base_price) {
            return false;
        }

        $now = now();
        
        if ($this->sale_starts_at && $now->lt($this->sale_starts_at)) {
            return false;
        }
        
        if ($this->sale_ends_at && $now->gt($this->sale_ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Hitung total stok dari semua sizes
     */
    public function getTotalStockAttribute(): int
    {
        if (!$this->relationLoaded('sizes')) {
            return $this->sizes()->sum('available_stock');
        }
        
        return $this->sizes->sum('available_stock');
    }

    /**
     * Dapatkan status stok (in_stock, low_stock, out_of_stock)
     */
    public function getStockStatusAttribute(): string
    {
        $totalStock = $this->total_stock;
        
        if ($totalStock <= 0) {
            return 'out_of_stock';
        }
        
        if ($totalStock < 10) {
            return 'low_stock';
        }
        
        return 'in_stock';
    }

    /**
     * Format harga base_price ke Rupiah
     */
    public function getPriceFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->base_price, 0, ',', '.');
    }

    /**
     * Format harga sale_price ke Rupiah (jika ada)
     */
    public function getSalePriceFormattedAttribute(): ?string
    {
        if ($this->sale_price) {
            return 'Rp ' . number_format($this->sale_price, 0, ',', '.');
        }
        return null;
    }

    /**
     * Hitung persentase diskon
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if ($this->is_on_sale && $this->base_price > 0) {
            $discount = (($this->base_price - $this->sale_price) / $this->base_price) * 100;
            return (int) round($discount);
        }
        return null;
    }

    /**
     * Dapatkan URL gambar utama
     */
    public function getMainImageUrlAttribute(): ?string
    {
        $mainImage = $this->mainImage;
        
        if ($mainImage) {
            return $mainImage->url;
        }
        
        // Default image jika tidak ada gambar
        return asset('images/no-image.png');
    }

    // ======================== SCOPES ========================

    /**
     * Scope untuk produk aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('visibility', 'public');
    }

    /**
     * Scope untuk produk featured
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope untuk produk yang tersedia (in stock)
     */
    public function scopeInStock($query)
    {
        return $query->whereHas('sizes', function ($q) {
            $q->where('available_stock', '>', 0);
        });
    }

    /**
     * Scope untuk produk yang sedang diskon
     */
    public function scopeOnSale($query)
    {
        $now = now();
        
        return $query->whereNotNull('sale_price')
            ->where('sale_price', '>', 0)
            ->where('sale_price', '<', DB::raw('base_price'))
            ->where(function ($q) use ($now) {
                $q->whereNull('sale_starts_at')
                    ->orWhere('sale_starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('sale_ends_at')
                    ->orWhere('sale_ends_at', '>=', $now);
            });
    }

    /**
     * Scope untuk filter by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        if ($categoryId && $categoryId !== 'all') {
            return $query->where('category_id', $categoryId);
        }
        return $query;
    }

    /**
     * Scope untuk search
     */
    public function scopeSearch($query, $searchTerm)
    {
        if ($searchTerm) {
            return $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('sku', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('tags', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('short_description', 'LIKE', "%{$searchTerm}%");
            });
        }
        return $query;
    }

    /**
     * Scope untuk filter by price range
     */
    public function scopePriceBetween($query, $min, $max)
    {
        if ($min !== null) {
            $query->where('base_price', '>=', $min);
        }
        
        if ($max !== null) {
            $query->where('base_price', '<=', $max);
        }
        
        return $query;
    }

    /**
     * Scope untuk sorting
     */
    public function scopeSortBy($query, $sortBy, $order = 'desc')
    {
        return match ($sortBy) {
            'price' => $query->orderBy('base_price', $order),
            'name' => $query->orderBy('name', $order),
            'created_at' => $query->orderBy('created_at', $order),
            'popular' => $query->orderBy('views_count', 'desc'),
            'sold' => $query->orderBy('sales_count', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };
    }

    // ======================== METHODS ========================

    /**
     * Update stok berdasarkan sizes
     */
    public function updateStock(): void
    {
        foreach ($this->sizes as $size) {
            $size->available_stock = $size->stock - $size->reserved_stock;
            $size->save();
        }
    }

    /**
     * Reserve stok untuk suatu size
     */
    public function reserveStock(string $size, int $quantity): bool
    {
        $productSize = $this->sizes()->where('size', $size)->first();
        
        if (!$productSize || $productSize->available_stock < $quantity) {
            return false;
        }

        $productSize->reserved_stock += $quantity;
        $productSize->available_stock = $productSize->stock - $productSize->reserved_stock;
        $productSize->save();

        return true;
    }

    /**
     * Release reserved stock
     */
    public function releaseStock(string $size, int $quantity): bool
    {
        $productSize = $this->sizes()->where('size', $size)->first();
        
        if (!$productSize || $productSize->reserved_stock < $quantity) {
            return false;
        }

        $productSize->reserved_stock -= $quantity;
        $productSize->available_stock = $productSize->stock - $productSize->reserved_stock;
        $productSize->save();

        return true;
    }

    /**
     * Increment view count
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    /**
     * Increment sales count
     */
    public function incrementSales(int $quantity = 1): void
    {
        $this->increment('sales_count', $quantity);
    }

    /**
     * Check if size is available
     */
    public function isSizeAvailable(string $size, int $quantity = 1): bool
    {
        $productSize = $this->sizes()->where('size', $size)->first();
        
        if (!$productSize) {
            return false;
        }
        
        return $productSize->available_stock >= $quantity;
    }

    /**
     * Get available sizes
     */
    public function getAvailableSizes(): array
    {
        return $this->sizes()
            ->where('available_stock', '>', 0)
            ->pluck('size')
            ->toArray();
    }

    /**
     * Get stock by size
     */
    public function getStockBySize(string $size): int
    {
        $productSize = $this->sizes()->where('size', $size)->first();
        
        return $productSize ? $productSize->available_stock : 0;
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(): bool
    {
        $this->is_featured = !$this->is_featured;
        return $this->save();
    }

    /**
     * Toggle active status
     */
    public function toggleActive(): bool
    {
        $this->is_active = !$this->is_active;
        return $this->save();
    }
}
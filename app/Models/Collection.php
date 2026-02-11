<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Collection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_image',
        'banner_image',
        'type',
        'season',
        'start_date',
        'end_date',
        'is_active',
        'is_featured',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'metadata' => 'array',
    ];

    // BOOT
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($collection) {
            if (empty($collection->slug)) {
                $collection->slug = Str::slug($collection->name);
            }
        });
    }

    // RELATIONSHIPS
    public function products()
    {
        return $this->belongsToMany(Product::class, 'collection_products')
                    ->withPivot('sort_order')
                    ->withTimestamps();
    }

    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
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

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBySeason($query, $season)
    {
        return $query->where('season', $season);
    }

    // ACCESSORS
    public function getCoverImageUrlAttribute()
    {
        if ($this->cover_image) {
            return asset('storage/' . $this->cover_image);
        }
        return null;
    }

    public function getBannerImageUrlAttribute()
    {
        if ($this->banner_image) {
            return asset('storage/' . $this->banner_image);
        }
        return null;
    }

    public function getIsCurrentAttribute()
    {
        $startOk = !$this->start_date || $this->start_date <= now();
        $endOk = !$this->end_date || $this->end_date >= now();
        
        return $startOk && $endOk;
    }

    // HELPERS
    public function addProduct($productId, $sortOrder = 0)
    {
        $this->products()->attach($productId, ['sort_order' => $sortOrder]);
        return $this;
    }

    public function removeProduct($productId)
    {
        $this->products()->detach($productId);
        return $this;
    }

    public function updateProductOrder($productId, $sortOrder)
    {
        $this->products()->updateExistingPivot($productId, ['sort_order' => $sortOrder]);
        return $this;
    }

    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    }

    public function getActiveProductsCountAttribute()
    {
        return $this->products()->active()->count();
    }

    public function getSortedProducts()
    {
        return $this->products()
                    ->with('images')
                    ->active()
                    ->orderBy('collection_products.sort_order')
                    ->get();
    }

    public function updateCoverImage($file)
    {
        if ($this->cover_image) {
            \Storage::delete('public/' . $this->cover_image);
        }
        
        $path = $file->store('collections/covers', 'public');
        $this->update(['cover_image' => $path]);
        
        return $this;
    }

    public function updateBannerImage($file)
    {
        if ($this->banner_image) {
            \Storage::delete('public/' . $this->banner_image);
        }
        
        $path = $file->store('collections/banners', 'public');
        $this->update(['banner_image' => $path]);
        
        return $this;
    }
}
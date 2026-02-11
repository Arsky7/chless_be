<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'cover_image',
        'description',
        'origin',
        'website',
        'social_media',
        'is_active',
        'is_featured',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'social_media' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'metadata' => 'array',
    ];

    // BOOT
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    // RELATIONSHIPS
    public function products()
    {
        return $this->hasMany(Product::class);
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

    public function scopeLocal($query)
    {
        return $query->where('origin', 'local');
    }

    public function scopeInternational($query)
    {
        return $query->where('origin', 'international');
    }

    // ACCESSORS
    public function getLogoUrlAttribute()
    {
        if ($this->logo) {
            return asset('storage/' . $this->logo);
        }
        return null;
    }

    public function getCoverImageUrlAttribute()
    {
        if ($this->cover_image) {
            return asset('storage/' . $this->cover_image);
        }
        return null;
    }

    // HELPERS
    public function getProductCountAttribute()
    {
        return $this->products()->count();
    }

    public function getActiveProductCountAttribute()
    {
        return $this->products()->active()->count();
    }

    public function updateLogo($file)
    {
        if ($this->logo) {
            \Storage::delete('public/' . $this->logo);
        }
        
        $path = $file->store('brands/logos', 'public');
        $this->update(['logo' => $path]);
        
        return $this;
    }

    public function getSocialMediaLink($platform)
    {
        $social = $this->social_media ?? [];
        return $social[$platform] ?? null;
    }
}
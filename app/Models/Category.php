<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'type',
        'is_active',
        'is_featured',
        'sort_order',
        'attributes',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'attributes' => 'array',
    ];

    // BOOT
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    // RELATIONSHIPS
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

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

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // ACCESSORS
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    public function getBreadcrumbsAttribute()
    {
        $breadcrumbs = [];
        $current = $this;
        
        while ($current) {
            $breadcrumbs[] = $current;
            $current = $current->parent;
        }
        
        return array_reverse($breadcrumbs);
    }

    // HELPERS
    public function getProductCountAttribute()
    {
        return $this->products()->count();
    }

    public function getAllChildrenIds()
    {
        $ids = [$this->id];
        
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllChildrenIds());
        }
        
        return $ids;
    }

    public function getDescendantProducts()
    {
        $categoryIds = $this->getAllChildrenIds();
        return Product::whereIn('category_id', $categoryIds);
    }

    public function updateImage($file)
    {
        if ($this->image) {
            \Storage::delete('public/' . $this->image);
        }
        
        $path = $file->store('categories', 'public');
        $this->update(['image' => $path]);
        
        return $this;
    }
}
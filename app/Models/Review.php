<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'title',
        'comment',
        'images',
        'is_approved',
        'is_featured',
        'helpful_count',
        'report_count',
    ];

    protected $casts = [
        'rating' => 'integer',
        'images' => 'array',
        'is_approved' => 'boolean',
        'is_featured' => 'boolean',
        'helpful_count' => 'integer',
        'report_count' => 'integer',
    ];

    // RELATIONSHIPS
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // SCOPES
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ACCESSORS
    public function getRatingStarsAttribute()
    {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $stars .= $i <= $this->rating ? '★' : '☆';
        }
        return $stars;
    }

    public function getImagesUrlsAttribute()
    {
        if (empty($this->images)) {
            return [];
        }
        
        return array_map(function($image) {
            return asset('storage/' . $image);
        }, $this->images);
    }

    public function getHelpfulPercentageAttribute()
    {
        $total = $this->helpful_count + $this->report_count;
        return $total > 0 ? round(($this->helpful_count / $total) * 100) : 0;
    }

    // HELPERS
    public function incrementHelpful()
    {
        $this->increment('helpful_count');
        return $this;
    }

    public function incrementReport()
    {
        $this->increment('report_count');
        
        // Auto hide if too many reports
        if ($this->report_count >= 5) {
            $this->update(['is_approved' => false]);
        }
        
        return $this;
    }

    public function approve()
    {
        $this->update(['is_approved' => true]);
        return $this;
    }

    public function reject()
    {
        $this->update(['is_approved' => false]);
        return $this;
    }

    public function feature()
    {
        $this->update(['is_featured' => true]);
        return $this;
    }

    public function unfeature()
    {
        $this->update(['is_featured' => false]);
        return $this;
    }

    public function addImage($file)
    {
        $images = $this->images ?? [];
        $path = $file->store('reviews', 'public');
        $images[] = $path;
        
        $this->update(['images' => $images]);
        return $this;
    }

    public function hasPurchasedProduct()
    {
        return $this->user->hasPurchasedProduct($this->product_id);
    }
}
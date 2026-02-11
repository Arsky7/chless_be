<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'position',
        'company',
        'testimonial',
        'avatar',
        'rating',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeHighRating($query, $minRating = 4)
    {
        return $query->where('rating', '>=', $minRating);
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // ACCESSORS
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }

    public function getRatingStarsAttribute()
    {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $stars .= $i <= $this->rating ? '★' : '☆';
        }
        return $stars;
    }

    public function getTestimonialExcerptAttribute()
    {
        return \Str::limit($this->testimonial, 100);
    }

    public function getPositionCompanyAttribute()
    {
        $parts = [];
        
        if ($this->position) {
            $parts[] = $this->position;
        }
        
        if ($this->company) {
            $parts[] = $this->company;
        }
        
        return implode(' at ', $parts);
    }

    // HELPERS
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

    public function activate()
    {
        $this->update(['is_active' => true]);
        return $this;
    }

    public function deactivate()
    {
        $this->update(['is_active' => false]);
        return $this;
    }

    public function moveUp()
    {
        $previous = self::where('sort_order', '<', $this->sort_order)
                        ->active()
                        ->orderBy('sort_order', 'desc')
                        ->first();
        
        if ($previous) {
            $currentOrder = $this->sort_order;
            $this->update(['sort_order' => $previous->sort_order]);
            $previous->update(['sort_order' => $currentOrder]);
        }
        
        return $this;
    }

    public function moveDown()
    {
        $next = self::where('sort_order', '>', $this->sort_order)
                    ->active()
                    ->orderBy('sort_order')
                    ->first();
        
        if ($next) {
            $currentOrder = $this->sort_order;
            $this->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $currentOrder]);
        }
        
        return $this;
    }

    public function updateAvatar($file)
    {
        if ($this->avatar) {
            \Storage::delete('public/' . $this->avatar);
        }
        
        $path = $file->store('testimonials', 'public');
        $this->update(['avatar' => $path]);
        
        return $this;
    }

    public static function getFeaturedTestimonials($limit = 6)
    {
        return self::featured()
                    ->active()
                    ->sorted()
                    ->limit($limit)
                    ->get();
    }

    public static function getRandomTestimonials($limit = 3)
    {
        return self::active()
                    ->highRating()
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get();
    }

    public function isHighRating()
    {
        return $this->rating >= 4;
    }

    public function getVerifiedBadge()
    {
        // You could implement verification logic here
        return $this->rating >= 4 ? 'verified' : '';
    }
}
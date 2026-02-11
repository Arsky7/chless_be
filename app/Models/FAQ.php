<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FAQ extends Model
{
    use HasFactory;

    protected $table = 'faqs';
    
    protected $fillable = [
        'question',
        'answer',
        'category',
        'sort_order',
        'is_active',
        'view_count',
        'helpful_count',
        'unhelpful_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'view_count' => 'integer',
        'helpful_count' => 'integer',
        'unhelpful_count' => 'integer',
    ];

    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopePopular($query, $limit = 10)
    {
        return $query->active()
                     ->orderBy('view_count', 'desc')
                     ->limit($limit);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('question', 'like', "%{$search}%")
              ->orWhere('answer', 'like', "%{$search}%");
        });
    }

    // ACCESSORS
    public function getCategoriesAttribute()
    {
        return self::distinct('category')
                    ->whereNotNull('category')
                    ->pluck('category');
    }

    public function getHelpfulPercentageAttribute()
    {
        $total = $this->helpful_count + $this->unhelpful_count;
        
        if ($total === 0) {
            return 0;
        }
        
        return round(($this->helpful_count / $total) * 100);
    }

    // HELPERS
    public function incrementViewCount()
    {
        $this->increment('view_count');
        return $this;
    }

    public function markAsHelpful()
    {
        $this->increment('helpful_count');
        return $this;
    }

    public function markAsUnhelpful()
    {
        $this->increment('unhelpful_count');
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
                        ->where('category', $this->category)
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
                    ->where('category', $this->category)
                    ->orderBy('sort_order')
                    ->first();
        
        if ($next) {
            $currentOrder = $this->sort_order;
            $this->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $currentOrder]);
        }
        
        return $this;
    }

    public static function getCategoriesWithCount()
    {
        return self::active()
                    ->select('category')
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('category')
                    ->orderBy('category')
                    ->get()
                    ->map(function($item) {
                        return [
                            'name' => $item->category ?: 'General',
                            'count' => $item->count,
                        ];
                    });
    }

    public static function searchFAQs($query, $category = null)
    {
        $search = self::search($query);
        
        if ($category) {
            $search->where('category', $category);
        }
        
        return $search->active()->sorted()->get();
    }

    public function getRelatedFAQs($limit = 5)
    {
        return self::where('id', '!=', $this->id)
                    ->where('category', $this->category)
                    ->active()
                    ->sorted()
                    ->limit($limit)
                    ->get();
    }

    public function isHelpful()
    {
        return $this->helpful_count > $this->unhelpful_count;
    }
}
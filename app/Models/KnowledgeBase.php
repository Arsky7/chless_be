<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KnowledgeBase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'knowledge_base';
    
    protected $fillable = [
        'title',
        'slug',
        'content',
        'category',
        'tags',
        'author',
        'is_published',
        'view_count',
        'helpful_count',
        'unhelpful_count',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_published' => 'boolean',
        'view_count' => 'integer',
        'helpful_count' => 'integer',
        'unhelpful_count' => 'integer',
    ];

    // BOOT
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($kb) {
            if (empty($kb->slug)) {
                $kb->slug = Str::slug($kb->title);
            }
        });
    }

    // SCOPES
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByTag($query, $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function scopePopular($query, $limit = 10)
    {
        return $query->published()
                     ->orderBy('view_count', 'desc')
                     ->limit($limit);
    }

    public function scopeHelpful($query, $limit = 10)
    {
        return $query->published()
                     ->orderBy('helpful_count', 'desc')
                     ->limit($limit);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('content', 'like', "%{$search}%")
              ->orWhereJsonContains('tags', $search);
        });
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // ACCESSORS
    public function getUrlAttribute()
    {
        return route('knowledge.base.show', $this->slug);
    }

    public function getContentExcerptAttribute()
    {
        return Str::limit(strip_tags($this->content), 200);
    }

    public function getTagsListAttribute()
    {
        return $this->tags ?? [];
    }

    public function getHelpfulPercentageAttribute()
    {
        $total = $this->helpful_count + $this->unhelpful_count;
        
        if ($total === 0) {
            return 0;
        }
        
        return round(($this->helpful_count / $total) * 100);
    }

    public function getCategoriesAttribute()
    {
        return self::distinct('category')
                    ->whereNotNull('category')
                    ->pluck('category');
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

    public function publish()
    {
        $this->update(['is_published' => true]);
        return $this;
    }

    public function unpublish()
    {
        $this->update(['is_published' => false]);
        return $this;
    }

    public function addTag($tag)
    {
        $tags = $this->tags ?? [];
        
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
        
        return $this;
    }

    public function removeTag($tag)
    {
        $tags = $this->tags ?? [];
        $tags = array_diff($tags, [$tag]);
        
        $this->update(['tags' => array_values($tags)]);
        return $this;
    }

    public function setCategory($category)
    {
        $this->update(['category' => $category]);
        return $this;
    }

    public function getRelatedArticles($limit = 5)
    {
        return self::where('id', '!=', $this->id)
                    ->where('category', $this->category)
                    ->published()
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get();
    }

    public static function getCategoriesWithCount()
    {
        return self::published()
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

    public static function searchArticles($query, $category = null, $tag = null)
    {
        $search = self::search($query);
        
        if ($category) {
            $search->where('category', $category);
        }
        
        if ($tag) {
            $search->whereJsonContains('tags', $tag);
        }
        
        return $search->published()->orderBy('view_count', 'desc')->get();
    }

    public function isHelpful()
    {
        return $this->helpful_count > $this->unhelpful_count;
    }

    public function getReadingTime()
    {
        $words = str_word_count(strip_tags($this->content));
        $minutes = ceil($words / 200); // Average reading speed: 200 words per minute
        
        return $minutes . ' min';
    }
}
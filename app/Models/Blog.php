<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Blog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'author',
        'author_id',
        'is_published',
        'published_at',
        'view_count',
        'like_count',
        'comment_count',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
    ];

    // BOOT
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($blog) {
            if (empty($blog->slug)) {
                $blog->slug = Str::slug($blog->title);
            }
            
            if ($blog->is_published && empty($blog->published_at)) {
                $blog->published_at = now();
            }
        });
    }

    // RELATIONSHIPS
    public function authorUser()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // SCOPES
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->where('published_at', '<=', now());
    }

    public function scopeDraft($query)
    {
        return $query->where('is_published', false);
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->published()
                     ->orderBy('published_at', 'desc')
                     ->limit($limit);
    }

    public function scopePopular($query, $limit = 10)
    {
        return $query->published()
                     ->orderBy('view_count', 'desc')
                     ->limit($limit);
    }

    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    // ACCESSORS
    public function getFeaturedImageUrlAttribute()
    {
        if ($this->featured_image) {
            return asset('storage/' . $this->featured_image);
        }
        return asset('images/default-blog.jpg');
    }

    public function getReadingTimeAttribute()
    {
        $words = str_word_count(strip_tags($this->content));
        $minutes = ceil($words / 200); // Average reading speed: 200 words per minute
        
        return $minutes . ' min read';
    }

    public function getPublishedDateFormattedAttribute()
    {
        if (!$this->published_at) {
            return null;
        }
        
        return $this->published_at->translatedFormat('d F Y');
    }

    public function getExcerptShortAttribute()
    {
        $excerpt = $this->excerpt ?: strip_tags($this->content);
        return Str::limit($excerpt, 150);
    }

    public function getUrlAttribute()
    {
        return route('blog.show', $this->slug);
    }

    // HELPERS
    public function incrementViewCount()
    {
        $this->increment('view_count');
        return $this;
    }

    public function incrementLikeCount()
    {
        $this->increment('like_count');
        return $this;
    }

    public function decrementLikeCount()
    {
        if ($this->like_count > 0) {
            $this->decrement('like_count');
        }
        return $this;
    }

    public function incrementCommentCount()
    {
        $this->increment('comment_count');
        return $this;
    }

    public function decrementCommentCount()
    {
        if ($this->comment_count > 0) {
            $this->decrement('comment_count');
        }
        return $this;
    }

    public function publish()
    {
        $this->update([
            'is_published' => true,
            'published_at' => now(),
        ]);
        
        return $this;
    }

    public function unpublish()
    {
        $this->update(['is_published' => false]);
        return $this;
    }

    public function updateFeaturedImage($file)
    {
        if ($this->featured_image) {
            \Storage::delete('public/' . $this->featured_image);
        }
        
        $path = $file->store('blogs', 'public');
        $this->update(['featured_image' => $path]);
        
        return $this;
    }

    public function getRelatedPosts($limit = 3)
    {
        return self::where('id', '!=', $this->id)
                    ->published()
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get();
    }

    public function isPublished()
    {
        return $this->is_published && $this->published_at <= now();
    }

    public function getAuthorNameAttribute()
    {
        return $this->author ?: ($this->authorUser->name ?? 'Admin');
    }

    public function getAuthorAvatarAttribute()
    {
        if ($this->authorUser && $this->authorUser->profile) {
            return $this->authorUser->profile->avatar_url;
        }
        return null;
    }
}
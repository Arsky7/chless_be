<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'template',
        'is_published',
        'is_homepage',
        'show_in_menu',
        'sort_order',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_homepage' => 'boolean',
        'show_in_menu' => 'boolean',
    ];

    // BOOT
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
            
            // If setting as homepage, remove homepage from other pages
            if ($page->is_homepage) {
                self::where('is_homepage', true)->update(['is_homepage' => false]);
            }
        });
        
        static::updating(function ($page) {
            // If setting as homepage, remove homepage from other pages
            if ($page->is_homepage) {
                self::where('id', '!=', $page->id)->update(['is_homepage' => false]);
            }
        });
    }

    // SCOPES
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeInMenu($query)
    {
        return $query->where('show_in_menu', true)
                     ->published()
                     ->orderBy('sort_order')
                     ->orderBy('title');
    }

    public function scopeHomepage($query)
    {
        return $query->where('is_homepage', true)->published();
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    // ACCESSORS
    public function getUrlAttribute()
    {
        if ($this->is_homepage) {
            return url('/');
        }
        return route('page.show', $this->slug);
    }

    public function getContentExcerptAttribute()
    {
        return Str::limit(strip_tags($this->content), 200);
    }

    public function getTemplateNameAttribute()
    {
        $templates = [
            'default' => 'Default',
            'contact' => 'Contact Page',
            'about' => 'About Page',
            'faq' => 'FAQ Page',
            'terms' => 'Terms & Conditions',
            'privacy' => 'Privacy Policy',
        ];
        
        return $templates[$this->template] ?? ucfirst($this->template);
    }

    // HELPERS
    public function setAsHomepage()
    {
        self::where('is_homepage', true)->update(['is_homepage' => false]);
        $this->update(['is_homepage' => true]);
        
        return $this;
    }

    public function removeFromHomepage()
    {
        $this->update(['is_homepage' => false]);
        return $this;
    }

    public function showInMenu()
    {
        $this->update(['show_in_menu' => true]);
        return $this;
    }

    public function hideFromMenu()
    {
        $this->update(['show_in_menu' => false]);
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

    public function moveUp()
    {
        $previous = self::where('sort_order', '<', $this->sort_order)
                        ->where('show_in_menu', true)
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
                    ->where('show_in_menu', true)
                    ->orderBy('sort_order')
                    ->first();
        
        if ($next) {
            $currentOrder = $this->sort_order;
            $this->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $currentOrder]);
        }
        
        return $this;
    }

    public static function getMenuItems()
    {
        return self::inMenu()->get()->map(function($page) {
            return [
                'title' => $page->title,
                'url' => $page->url,
                'slug' => $page->slug,
                'is_homepage' => $page->is_homepage,
            ];
        });
    }

    public static function getHomepage()
    {
        return self::homepage()->first();
    }

    public function hasCustomTemplate()
    {
        return $this->template && $this->template !== 'default';
    }

    public function getTemplateView()
    {
        return 'pages.templates.' . $this->template;
    }
}
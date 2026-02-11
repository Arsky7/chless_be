<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'birth_date',
        'gender',
        'avatar',
        'bio',
        'occupation',
        'social_links',
        'preferences',
        'timezone',
        'language',
        'currency',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'social_links' => 'array',
        'preferences' => 'array',
    ];

    // RELATIONSHIPS
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ACCESSORS
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                if ($attributes['avatar']) {
                    return asset('storage/' . $attributes['avatar']);
                }
                return 'https://ui-avatars.com/api/?name=' . urlencode($this->user->name) . '&color=7F9CF5&background=EBF4FF';
            }
        );
    }

    protected function age(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->birth_date) return null;
                return $this->birth_date->age;
            }
        );
    }

    // HELPERS
    public function getSocialLink($platform)
    {
        $links = $this->social_links ?? [];
        return $links[$platform] ?? null;
    }

    public function updateAvatar($file)
    {
        if ($this->avatar) {
            \Storage::delete('public/' . $this->avatar);
        }
        
        $path = $file->store('avatars', 'public');
        $this->update(['avatar' => $path]);
        
        return $this;
    }
}
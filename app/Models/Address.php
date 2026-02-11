<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'province',
        'city',
        'district',
        'subdistrict',
        'street_address',
        'postal_code',
        'is_default',
        'notes',
        'coordinates',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'coordinates' => 'array',
    ];

    // RELATIONSHIPS
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'address_id');
    }

    // SCOPES
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // HELPERS
    public function getFullAddressAttribute()
    {
        $parts = [
            $this->street_address,
            $this->subdistrict,
            $this->district,
            $this->city,
            $this->province,
            $this->postal_code,
        ];
        
        return implode(', ', array_filter($parts));
    }

    public function setAsDefault()
    {
        // Remove default from other addresses
        $this->user->addresses()->update(['is_default' => false]);
        
        // Set this as default
        $this->update(['is_default' => true]);
        
        return $this;
    }

    public function getFormattedPhoneAttribute()
    {
        $phone = $this->phone;
        if (str_starts_with($phone, '0')) {
            return '+62' . substr($phone, 1);
        }
        return $phone;
    }
}
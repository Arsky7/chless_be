<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'receiver_name',
        'receiver_phone',
        'province',
        'province_code',
        'city',
        'city_code',
        'district',
        'district_code',
        'village',
        'postal_code',
        'full_address',
        'latitude',
        'longitude',
        'is_default',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

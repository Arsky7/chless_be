<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FlashSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'banner',
        'start_time',
        'end_time',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'status',
        'total_products',
        'total_sold',
        'total_revenue',
        'created_by'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'total_products' => 'integer',
        'total_sold' => 'integer',
        'total_revenue' => 'decimal:2',
    ];

    protected $appends = [
        'is_active',
        'is_ended',
        'is_upcoming',
        'progress_percentage'
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(ProductSize::class, 'flash_sale_products', 'flash_sale_id', 'product_size_id')
                    ->withPivot('special_price', 'max_quantity_per_user', 'total_quota', 'sold_quantity', 'status')
                    ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getIsActiveAttribute()
    {
        $now = now();
        return $this->status === 'active' && 
               $this->start_time <= $now && 
               $this->end_time >= $now;
    }

    public function getIsEndedAttribute()
    {
        return $this->end_time < now() || $this->status === 'ended';
    }

    public function getIsUpcomingAttribute()
    {
        return $this->start_time > now() && $this->status === 'scheduled';
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->is_ended) {
            return 100;
        }
        
        if ($this->is_upcoming) {
            return 0;
        }
        
        $totalDuration = $this->start_time->diffInSeconds($this->end_time);
        $elapsedDuration = $this->start_time->diffInSeconds(now());
        
        if ($totalDuration <= 0) {
            return 0;
        }
        
        return min(100, round(($elapsedDuration / $totalDuration) * 100));
    }

    public function getStatusBadgeAttribute()
    {
        if ($this->is_active) {
            return '<span class="badge badge-success">Active</span>';
        }
        
        if ($this->is_ended) {
            return '<span class="badge badge-secondary">Ended</span>';
        }
        
        if ($this->is_upcoming) {
            return '<span class="badge badge-info">Upcoming</span>';
        }
        
        return [
            'draft' => '<span class="badge badge-warning">Draft</span>',
            'cancelled' => '<span class="badge badge-danger">Cancelled</span>',
        ][$this->status] ?? '<span class="badge badge-secondary">' . ucfirst($this->status) . '</span>';
    }
}

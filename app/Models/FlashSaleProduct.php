<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleProduct extends Model
{
    use HasFactory;

    protected $table = 'flash_sale_products';

    protected $fillable = [
        'flash_sale_id',
        'product_size_id',
        'special_price',
        'max_quantity_per_user',
        'total_quota',
        'sold_quantity',
        'status'
    ];

    protected $casts = [
        'special_price' => 'decimal:2',
        'max_quantity_per_user' => 'integer',
        'total_quota' => 'integer',
        'sold_quantity' => 'integer',
    ];

    protected $appends = [
        'remaining_quota',
        'discount_percentage',
        'original_price'
    ];

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function getRemainingQuotaAttribute()
    {
        return $this->total_quota ? $this->total_quota - $this->sold_quantity : null;
    }

    public function getDiscountPercentageAttribute()
    {
        $originalPrice = $this->productSize->final_price;
        
        if ($originalPrice <= 0) {
            return 0;
        }
        
        return round((($originalPrice - $this->special_price) / $originalPrice) * 100);
    }

    public function getOriginalPriceAttribute()
    {
        return $this->productSize->final_price;
    }

    public function getIsSoldOutAttribute()
    {
        return $this->total_quota && $this->sold_quantity >= $this->total_quota;
    }

    public function getStatusBadgeAttribute()
    {
        if ($this->is_sold_out) {
            return '<span class="badge badge-secondary">Sold Out</span>';
        }
        
        return [
            'active' => '<span class="badge badge-success">Active</span>',
            'paused' => '<span class="badge badge-warning">Paused</span>',
            'ended' => '<span class="badge badge-secondary">Ended</span>',
        ][$this->status] ?? '<span class="badge badge-secondary">' . ucfirst($this->status) . '</span>';
    }
}

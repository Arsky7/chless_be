<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'discount_id',
        'code',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    // RELATIONSHIPS
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    // SCOPES
    public function scopeUsed($query)
    {
        return $query->whereNotNull('used_at');
    }

    public function scopeUnused($query)
    {
        return $query->whereNull('used_at');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByDiscount($query, $discountId)
    {
        return $query->where('discount_id', $discountId);
    }

    public function scopeValid($query)
    {
        return $query->whereNull('used_at')
                     ->whereHas('discount', function($q) {
                         $q->where('is_active', true)
                           ->where('start_date', '<=', now())
                           ->where('end_date', '>=', now());
                     });
    }

    // ACCESSORS
    public function getIsUsedAttribute()
    {
        return !is_null($this->used_at);
    }

    public function getIsValidAttribute()
    {
        return !$this->is_used && 
               $this->discount && 
               $this->discount->is_valid;
    }

    // HELPERS
    public function markAsUsed()
    {
        if ($this->is_used) {
            return $this;
        }
        
        $this->update([
            'used_at' => now(),
        ]);
        
        // Increment discount usage
        if ($this->discount) {
            $this->discount->incrementUsage();
        }
        
        return $this;
    }

    public function markAsUnused()
    {
        if (!$this->is_used) {
            return $this;
        }
        
        $this->update([
            'used_at' => null,
        ]);
        
        // Decrement discount usage
        if ($this->discount) {
            $this->discount->decrementUsage();
        }
        
        return $this;
    }

    public static function generateCoupons($discountId, $quantity = 1, $userId = null)
    {
        $discount = Discount::findOrFail($discountId);
        $coupons = [];
        
        for ($i = 0; $i < $quantity; $i++) {
            $code = $discount->code . '-' . strtoupper(uniqid());
            
            $coupon = self::create([
                'user_id' => $userId,
                'discount_id' => $discountId,
                'code' => $code,
            ]);
            
            $coupons[] = $coupon;
        }
        
        return $coupons;
    }

    public static function validateCoupon($code, $userId = null)
    {
        $coupon = self::where('code', $code)->valid()->first();
        
        if (!$coupon) {
            return [
                'success' => false,
                'message' => 'Kupon tidak valid atau sudah digunakan',
            ];
        }
        
        // Check if coupon is assigned to specific user
        if ($coupon->user_id && $coupon->user_id != $userId) {
            return [
                'success' => false,
                'message' => 'Kupon ini tidak berlaku untuk akun Anda',
            ];
        }
        
        return [
            'success' => true,
            'coupon' => $coupon,
            'discount' => $coupon->discount,
            'message' => 'Kupon valid',
        ];
    }

    public function applyToOrder($order)
    {
        if (!$this->is_valid) {
            throw new \Exception('Kupon tidak valid');
        }
        
        $discountAmount = $this->discount->calculateDiscount($order->subtotal);
        
        $order->update([
            'discount_amount' => $discountAmount,
            'total_amount' => $order->subtotal + $order->shipping_cost + $order->tax_amount - $discountAmount,
            'metadata' => array_merge($order->metadata ?? [], [
                'coupon_used' => $this->id,
                'discount_applied' => $this->discount_id,
            ]),
        ]);
        
        // Mark coupon as used
        $this->markAsUsed();
        
        return $discountAmount;
    }

    public function getFormattedDiscountAttribute()
    {
        if (!$this->discount) {
            return null;
        }
        
        return $this->discount->formatted_value;
    }

    public function getUserNameAttribute()
    {
        if (!$this->user) {
            return 'Umum';
        }
        
        return $this->user->name;
    }
}
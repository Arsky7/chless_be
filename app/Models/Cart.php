<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'coupon_id',
        'discount_amount',
        'subtotal',
        'total',
        'expires_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    protected $appends = [
        'items_count',
        'total_quantity'
    ];

    // ============ RELATIONSHIPS ============
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    // ============ ACCESSORS ============
    
    public function getItemsCountAttribute()
    {
        return $this->items()->count();
    }

    public function getTotalQuantityAttribute()
    {
        return $this->items()->sum('quantity');
    }

    public function getFormattedSubtotalAttribute()
    {
        return 'Rp ' . number_format($this->subtotal, 0, ',', '.');
    }

    public function getFormattedTotalAttribute()
    {
        return 'Rp ' . number_format($this->total, 0, ',', '.');
    }

    // ============ SCOPES ============
    
    public function scopeActive($query)
    {
        return $query->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBySession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    // ============ HELPERS ============
    
    public static function getOrCreateCart($userId = null, $sessionId = null)
    {
        if ($userId) {
            $cart = self::where('user_id', $userId)->active()->first();
        } elseif ($sessionId) {
            $cart = self::where('session_id', $sessionId)->active()->first();
        } else {
            $cart = null;
        }
        
        if (!$cart) {
            $cart = self::create([
                'user_id' => $userId,
                'session_id' => $sessionId ?? session()->getId(),
                'subtotal' => 0,
                'total' => 0,
                'expires_at' => now()->addDays(7),
            ]);
        }
        
        return $cart;
    }

    public function addItem($productSizeId, $quantity = 1)
    {
        $productSize = ProductSize::findOrFail($productSizeId);
        
        if (!$productSize->is_active) {
            throw new \Exception('Product is not available');
        }
        
        $cartItem = $this->items()
            ->where('product_size_id', $productSizeId)
            ->first();

        if ($cartItem) {
            $cartItem->increment('quantity', $quantity);
        } else {
            $this->items()->create([
                'product_size_id' => $productSizeId,
                'quantity' => $quantity
            ]);
        }

        $this->refresh();
        $this->updateTotals();
        
        return $this;
    }

    public function updateItemQuantity($productSizeId, $quantity)
    {
        $cartItem = $this->items()
            ->where('product_size_id', $productSizeId)
            ->first();
        
        if (!$cartItem) {
            throw new \Exception('Item not found in cart');
        }
        
        if ($quantity <= 0) {
            $cartItem->delete();
        } else {
            $cartItem->update(['quantity' => $quantity]);
        }
        
        $this->updateTotals();
        
        return $this;
    }

    public function removeItem($productSizeId)
    {
        return $this->updateItemQuantity($productSizeId, 0);
    }

    public function clear()
    {
        $this->items()->delete();
        $this->update([
            'coupon_id' => null,
            'discount_amount' => 0,
            'subtotal' => 0,
            'total' => 0,
        ]);
        
        return $this;
    }

    public function updateTotals()
    {
        $subtotal = 0;
        
        foreach ($this->items as $item) {
            $subtotal += $item->subtotal;
        }
        
        $this->subtotal = $subtotal;
        $this->total = $subtotal - ($this->discount_amount ?? 0);
        $this->save();
    }

    public function applyCoupon($couponCode)
    {
        $coupon = Coupon::where('code', $couponCode)
            ->active()
            ->first();
        
        if (!$coupon) {
            throw new \Exception('Invalid coupon code');
        }
        
        if ($coupon->min_order_amount && $this->subtotal < $coupon->min_order_amount) {
            throw new \Exception('Minimum order amount not reached');
        }
        
        $discountAmount = $coupon->calculateDiscount($this->subtotal);
        
        $this->update([
            'coupon_id' => $coupon->id,
            'discount_amount' => $discountAmount,
            'total' => $this->subtotal - $discountAmount,
        ]);
        
        return $this;
    }

    public function removeCoupon()
    {
        $this->update([
            'coupon_id' => null,
            'discount_amount' => 0,
            'total' => $this->subtotal,
        ]);
        
        return $this;
    }

    public function convertToOrder($addressId, $shippingMethodId, $notes = null)
    {
        $order = Order::create([
            'user_id' => $this->user_id,
            'address_id' => $addressId,
            'shipping_method_id' => $shippingMethodId,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount ?? 0,
            'total_amount' => $this->total,
            'status' => 'pending',
            'payment_status' => 'pending',
            'customer_notes' => $notes,
            'metadata' => [
                'cart_id' => $this->id,
                'coupon_id' => $this->coupon_id,
            ],
        ]);
        
        foreach ($this->items as $item) {
            $order->addItem(
                $item->product_size_id,
                $item->quantity,
                $item->productSize->final_price
            );
        }
        
        $this->clear();
        
        return $order;
    }
}
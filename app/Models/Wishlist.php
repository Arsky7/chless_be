<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'notes',
    ];

    protected $casts = [
        'notes' => 'array',
    ];

    // RELATIONSHIPS
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // SCOPES
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // HELPERS
    public static function addToWishlist($userId, $productId, $notes = null)
    {
        $existing = self::where('user_id', $userId)
                        ->where('product_id', $productId)
                        ->first();
        
        if ($existing) {
            return $existing;
        }
        
        return self::create([
            'user_id' => $userId,
            'product_id' => $productId,
            'notes' => $notes,
        ]);
    }

    public static function removeFromWishlist($userId, $productId)
    {
        return self::where('user_id', $userId)
                    ->where('product_id', $productId)
                    ->delete();
    }

    public static function getUserWishlist($userId)
    {
        return self::with('product')
                    ->where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    public static function getWishlistCount($userId)
    {
        return self::where('user_id', $userId)->count();
    }

    public function isProductInStock()
    {
        return $this->product->is_in_stock;
    }

    public function moveToCart($quantity = 1, $options = [])
    {
        $cart = Cart::getOrCreateCart($this->user_id);
        
        // Add product to cart
        $cart->addItem($this->product_id, $quantity, $options);
        
        // Remove from wishlist
        $this->delete();
        
        return $cart;
    }
}
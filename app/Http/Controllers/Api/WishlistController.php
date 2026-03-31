<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    /**
     * Display a listing of the user's wishlist.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get all wishlist items for the authenticated user
        // Load the associated product with its main image and category
        $wishlists = Wishlist::with(['product' => function ($query) {
            $query->with(['category', 'images' => function ($imgQuery) {
                // Ensure we get the main image or at least some image
                $imgQuery->where('is_main', true)->orWhere('is_main', false)->orderBy('is_main', 'desc')->take(1);
            }]);
        }])
        ->where('user_id', $user->id)
        ->latest()
        ->get();

        return response()->json([
            'success' => true,
            'data' => $wishlists
        ]);
    }

    /**
     * Toggle a product on the user's wishlist.
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = $request->user();
        $productId = $request->product_id;

        // Check if it already exists
        $wishlist = Wishlist::where('user_id', $user->id)
                            ->where('product_id', $productId)
                            ->first();

        if ($wishlist) {
            // Remove from wishlist
            $wishlist->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Product removed from wishlist.',
                'action' => 'removed'
            ]);
        } else {
            // Add to wishlist
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product added to wishlist.',
                'action' => 'added'
            ], 201);
        }
    }
}

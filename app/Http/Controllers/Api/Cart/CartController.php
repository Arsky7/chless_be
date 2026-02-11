<?php

namespace App\Http\Controllers\Api\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cart\AddToCartRequest;
use App\Http\Requests\Api\Cart\UpdateCartItemRequest;
use App\Http\Requests\Api\Cart\ApplyCouponRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\ProductSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Get current user cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $cart = Cart::getOrCreateCart($request->user()?->id, $request->session()->getId());

        return response()->json([
            'success' => true,
            'data' => new CartResource($cart->load('items.productSize.product', 'items.productSize.product.images', 'coupon'))
        ]);
    }

    /**
     * Add item to cart.
     *
     * @param AddToCartRequest $request
     * @return JsonResponse
     */
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $cart = Cart::getOrCreateCart($request->user()?->id, $request->session()->getId());

            $productSize = ProductSize::with('product')
                ->where('is_active', true)
                ->findOrFail($request->product_size_id);

            // Check stock
            if ($productSize->available_stock < $request->quantity) {
                return response()->json([
                    'message' => 'Insufficient stock. Available: ' . $productSize->available_stock,
                    'success' => false
                ], 422);
            }

            $cart->addItem($request->product_size_id, $request->quantity);

            return response()->json([
                'message' => 'Item added to cart',
                'success' => true,
                'data' => new CartResource($cart->load('items.productSize.product', 'items.productSize.product.images'))
            ]);
        });
    }

    /**
     * Update cart item quantity.
     *
     * @param UpdateCartItemRequest $request
     * @param int $itemId
     * @return JsonResponse
     */
    public function updateItem(UpdateCartItemRequest $request, $itemId): JsonResponse
    {
        return DB::transaction(function () use ($request, $itemId) {
            $cart = Cart::getOrCreateCart($request->user()?->id, $request->session()->getId());

            $cartItem = $cart->items()->findOrFail($itemId);

            // Check stock if increasing quantity
            if ($request->quantity > $cartItem->quantity) {
                $additional = $request->quantity - $cartItem->quantity;
                
                if ($cartItem->productSize->available_stock < $additional) {
                    return response()->json([
                        'message' => 'Insufficient stock. Available: ' . $cartItem->productSize->available_stock,
                        'success' => false
                    ], 422);
                }
            }

            $cart->updateItemQuantity($cartItem->product_size_id, $request->quantity);

            return response()->json([
                'message' => 'Cart updated',
                'success' => true,
                'data' => new CartResource($cart->load('items.productSize.product'))
            ]);
        });
    }

    /**
     * Remove item from cart.
     *
     * @param Request $request
     * @param int $itemId
     * @return JsonResponse
     */
    public function removeItem(Request $request, $itemId): JsonResponse
    {
        return DB::transaction(function () use ($request, $itemId) {
            $cart = Cart::getOrCreateCart($request->user()?->id, $request->session()->getId());

            $cartItem = $cart->items()->findOrFail($itemId);
            $cartItem->delete();

            $cart->updateTotals();

            return response()->json([
                'message' => 'Item removed from cart',
                'success' => true,
                'data' => new CartResource($cart->load('items.productSize.product'))
            ]);
        });
    }

    /**
     * Clear cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clear(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $cart = Cart::getOrCreateCart($request->user()?->id, $request->session()->getId());

            $cart->clear();

            return response()->json([
                'message' => 'Cart cleared',
                'success' => true,
                'data' => new CartResource($cart)
            ]);
        });
    }

    /**
     * Apply coupon to cart.
     *
     * @param ApplyCouponRequest $request
     * @return JsonResponse
     */
    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $cart = Cart::getOrCreateCart($request->user()?->id, $request->session()->getId());

            try {
                $cart->applyCoupon($request->coupon_code);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'success' => false
                ], 422);
            }

            return response()->json([
                'message' => 'Coupon applied successfully',
                'success' => true,
                'data' => new CartResource($cart->load('coupon'))
            ]);
        });
    }

    /**
     * Remove coupon from cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $cart = Cart::getOrCreateCart($request->user()?->id, $request->session()->getId());

            $cart->removeCoupon();

            return response()->json([
                'message' => 'Coupon removed',
                'success' => true,
                'data' => new CartResource($cart)
            ]);
        });
    }

    /**
     * Get cart summary.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $cart = Cart::getOrCreateCart($request->user()?->id, $request->session()->getId());

        $summary = [
            'subtotal' => $cart->subtotal,
            'subtotal_formatted' => $cart->formatted_subtotal,
            'discount_amount' => $cart->discount_amount ?? 0,
            'discount_formatted' => 'Rp ' . number_format($cart->discount_amount ?? 0, 0, ',', '.'),
            'total' => $cart->total,
            'total_formatted' => $cart->formatted_total,
            'items_count' => $cart->items_count,
            'total_quantity' => $cart->total_quantity
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}

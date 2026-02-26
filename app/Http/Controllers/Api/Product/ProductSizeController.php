<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Product\StoreProductSizeRequest;
use App\Http\Requests\Api\Product\UpdateProductSizeRequest;
use App\Http\Resources\ProductSizeResource;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductSizeController extends Controller
{
    /**
     * Display a listing of product sizes.
     *
     * @param int $productId
     * @return JsonResponse
     */
    public function index($productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        
        $sizes = $product->sizes()
            ->orderBy('size')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ProductSizeResource::collection($sizes),
            'meta' => [
                'current_page' => $sizes->currentPage(),
                'last_page' => $sizes->lastPage(),
                'total' => $sizes->total()
            ]
        ]);
    }

    /**
     * Store a newly created product size.
     *
     * @param StoreProductSizeRequest $request
     * @param int $productId
     * @return JsonResponse
     */
    public function store(StoreProductSizeRequest $request, $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $this->authorize('update', $product);

        $size = $product->sizes()->create([
            'size' => $request->size,
            'sku' => $request->sku,
            'price' => $request->price,
            'compare_price' => $request->compare_price,
            'cost' => $request->cost,
            'stock' => $request->stock ?? 0,
            'low_stock_threshold' => $request->low_stock_threshold ?? 5,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Product size created successfully',
            'success' => true,
            'data' => new ProductSizeResource($size)
        ], 201);
    }

    /**
     * Display the specified product size.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $size = ProductSize::with('product')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ProductSizeResource($size)
        ]);
    }

    /**
     * Update the specified product size.
     *
     * @param UpdateProductSizeRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateProductSizeRequest $request, $id): JsonResponse
    {
        $size = ProductSize::findOrFail($id);
        $this->authorize('update', $size->product);

        $size->update([
            'size' => $request->size ?? $size->size,
            'sku' => $request->sku ?? $size->sku,
            'price' => $request->price ?? $size->price,
            'compare_price' => $request->compare_price ?? $size->compare_price,
            'cost' => $request->cost ?? $size->cost,
            'stock' => $request->stock ?? $size->stock,
            'low_stock_threshold' => $request->low_stock_threshold ?? $size->low_stock_threshold,
            'is_active' => $request->is_active ?? $size->is_active,
        ]);

        return response()->json([
            'message' => 'Product size updated successfully',
            'success' => true,
            'data' => new ProductSizeResource($size)
        ]);
    }

    /**
     * Remove the specified product size.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $size = ProductSize::findOrFail($id);
        $this->authorize('delete', $size->product);

        $size->delete();

        return response()->json([
            'message' => 'Product size deleted successfully',
            'success' => true
        ]);
    }

    /**
     * Update stock.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStock(Request $request, $id): JsonResponse
    {
        $request->validate([
            'stock' => ['required', 'integer', 'min:0']
        ]);

        $size = ProductSize::findOrFail($id);
        $this->authorize('update', $size->product);

        $size->update(['stock' => $request->stock]);

        return response()->json([
            'message' => 'Stock updated successfully',
            'success' => true,
            'data' => [
                'stock' => $size->stock,
                'available_stock' => $size->available_stock
            ]
        ]);
    }
}

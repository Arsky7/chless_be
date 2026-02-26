<?php
// app/Http/Controllers/Admin/InventoryController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * GET /admin/inventory/stats
     * Returns summary counts for the stat cards.
     */
    public function stats()
    {
        try {
            $totalSkus   = ProductSize::count();
            $lowStock    = ProductSize::where('available_stock', '>', 0)
                                      ->where('available_stock', '<', 10)
                                      ->count();
            $critical    = ProductSize::where('available_stock', '>', 0)
                                      ->where('available_stock', '<', 5)
                                      ->count();
            $outOfStock  = ProductSize::where('available_stock', '<=', 0)->count();

            // Total stock value = SUM(products.base_price * sizes.stock)
            $totalValue = DB::table('product_sizes')
                ->join('products', 'product_sizes.product_id', '=', 'products.id')
                ->whereNull('products.deleted_at')
                ->sum(DB::raw('products.base_price * product_sizes.stock'));

            return response()->json([
                'success' => true,
                'data'    => [
                    'total_skus'      => (int) $totalSkus,
                    'low_stock'       => (int) $lowStock,
                    'critical'        => (int) $critical,
                    'out_of_stock'    => (int) $outOfStock,
                    'total_value'     => (float) $totalValue,
                    'total_value_formatted' => 'Rp ' . number_format($totalValue, 0, ',', '.'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load inventory stats',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /admin/inventory
     * Paginated, filterable list of product_sizes with product + category.
     * Query params: status (good|low|critical|out), category_id, search, per_page, page
     */
    public function index(Request $request)
    {
        try {
            $query = ProductSize::with(['product.category', 'product.images'])
                ->join('products', 'product_sizes.product_id', '=', 'products.id')
                ->whereNull('products.deleted_at')
                ->select(
                    'product_sizes.*',
                    'products.name as product_name',
                    'products.sku as product_sku',
                    'products.base_price',
                    'products.category_id',
                    'products.is_active',
                );

            // Filter by status
            if ($request->filled('status')) {
                switch ($request->status) {
                    case 'good':
                        $query->where('product_sizes.available_stock', '>=', 10);
                        break;
                    case 'low':
                        $query->where('product_sizes.available_stock', '>=', 5)
                              ->where('product_sizes.available_stock', '<', 10);
                        break;
                    case 'critical':
                        $query->where('product_sizes.available_stock', '>', 0)
                              ->where('product_sizes.available_stock', '<', 5);
                        break;
                    case 'out':
                        $query->where('product_sizes.available_stock', '<=', 0);
                        break;
                }
            }

            // Filter by category
            if ($request->filled('category_id')) {
                $query->where('products.category_id', $request->category_id);
            }

            // Search by product name or SKU
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('products.name', 'like', "%{$search}%")
                      ->orWhere('products.sku', 'like', "%{$search}%");
                });
            }

            $query->orderByRaw('product_sizes.available_stock ASC');

            $perPage = $request->get('per_page', 20);
            $paginated = $query->paginate($perPage);

            // Transform items to include computed status label and image
            $items = collect($paginated->items())->map(function ($size) {
                $stock = (int) $size->available_stock;
                if ($stock <= 0)       $status = 'out';
                elseif ($stock < 5)    $status = 'critical';
                elseif ($stock < 10)   $status = 'low';
                else                   $status = 'good';

                $product = $size->product;
                $mainImage = $product?->images?->firstWhere('is_main', true)
                          ?? $product?->images?->first();

                return [
                    'id'              => $size->id,
                    'product_id'      => $size->product_id,
                    'product_name'    => $product?->name ?? $size->product_name,
                    'product_sku'     => $product?->sku ?? $size->product_sku,
                    'category_id'     => $product?->category_id,
                    'category_name'   => $product?->category?->name ?? 'Uncategorized',
                    'image_url'       => $mainImage?->url ?? null,
                    'size'            => $size->size,
                    'stock'           => (int) $size->stock,
                    'reserved_stock'  => (int) $size->reserved_stock,
                    'available_stock' => $stock,
                    'base_price'      => (float) ($product?->base_price ?? $size->base_price),
                    'total_value'     => (float) (($product?->base_price ?? 0) * $size->stock),
                    'status'          => $status,
                    'is_active'       => (bool) ($product?->is_active ?? true),
                    'updated_at'      => $size->updated_at?->toDateString(),
                ];
            });

            return response()->json([
                'success'      => true,
                'data'         => $items,
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'message'      => 'Inventory retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve inventory',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /admin/inventory/{id}/restock
     * Add stock to a specific product_size.
     * Body: { quantity: int }
     */
    public function restock(Request $request, ProductSize $inventory)
    {
        try {
            $request->validate([
                'quantity' => 'required|integer|min:1',
            ]);

            DB::beginTransaction();

            $inventory->stock += (int) $request->quantity;
            $inventory->available_stock = $inventory->stock - $inventory->reserved_stock;
            $inventory->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock restocked successfully',
                'data'    => [
                    'id'              => $inventory->id,
                    'stock'           => $inventory->stock,
                    'available_stock' => $inventory->available_stock,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to restock',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /admin/inventory/{id}/adjust
     * Set an absolute stock quantity (for stock opname / correction).
     * Body: { stock: int, reason?: string }
     */
    public function adjust(Request $request, ProductSize $inventory)
    {
        try {
            $request->validate([
                'stock'  => 'required|integer|min:0',
                'reason' => 'nullable|string|max:255',
            ]);

            DB::beginTransaction();

            $inventory->stock           = (int) $request->stock;
            $inventory->available_stock = $inventory->stock - $inventory->reserved_stock;
            $inventory->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data'    => [
                    'id'              => $inventory->id,
                    'stock'           => $inventory->stock,
                    'available_stock' => $inventory->available_stock,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}

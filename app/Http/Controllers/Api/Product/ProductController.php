<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Product\StoreProductRequest;
use App\Http\Requests\Api\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductListResource;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'sizes', 'images'])
            ->where('is_active', true);

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }



        // Filter by gender
        if ($request->has('gender')) {
            $query->where('gender', $request->gender);
        }

        // Filter by color
        if ($request->has('color_hex')) {
            $query->where('color_hex', $request->color_hex);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortField, ['name', 'base_price', 'created_at', 'sold_count'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        // On sale filter
        if ($request->boolean('on_sale')) {
            $query->whereNotNull('sale_price')
                ->where(function($q) {
                    $q->whereNull('sale_starts_at')
                      ->orWhere('sale_starts_at', '<=', now());
                })
                ->where(function($q) {
                    $q->whereNull('sale_ends_at')
                      ->orWhere('sale_ends_at', '>=', now());
                });
        }

        $products = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => ProductListResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
                'per_page' => $products->perPage()
            ]
        ]);
    }

    /**
     * Display featured products.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function featured(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'sizes', 'images'])
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 10))
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProductListResource::collection($products)
        ]);
    }

    /**
     * Display best selling products.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bestSelling(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'sizes', 'images'])
            ->where('is_active', true)
            ->orderBy('sold_count', 'desc')
            ->limit($request->get('limit', 10))
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProductListResource::collection($products)
        ]);
    }

    /**
     * Display new arrivals.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function newArrivals(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'sizes', 'images'])
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 10))
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProductListResource::collection($products)
        ]);
    }

    /**
     * Display the specified product.
     *
     * @param string $slug
     * @return JsonResponse
     */
    public function show($slug): JsonResponse
    {
        $product = Product::with([
            'category', 
            'sizes' => function($q) {
                $q->where('is_active', true)->orderBy('size');
            },
            'images' => function($q) {
                $q->orderBy('is_main', 'desc')->orderBy('order');
            },
            'reviews' => function($q) {
                $q->where('is_approved', true)->latest()->limit(10);
            }
        ])
        ->where('slug', $slug)
        ->where('is_active', true)
        ->firstOrFail();

        // Increment view count
        $product->incrementViewCount();

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product)
        ]);
    }

    /**
     * Get related products.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function related($id, Request $request): JsonResponse
    {
        $product = Product::findOrFail($id);

        $related = Product::with(['category', 'images'])
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit($request->get('limit', 8))
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProductListResource::collection($related)
        ]);
    }

    /**
     * Store a newly created product.
     *
     * @param StoreProductRequest $request
     * @return JsonResponse
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        return DB::transaction(function () use ($request) {
            $product = Product::create([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'sku' => $request->sku,
                'description' => $request->description,
                'short_description' => $request->short_description,
                'base_price' => $request->base_price,
                'sale_price' => $request->sale_price,
                'sale_starts_at' => $request->sale_starts_at,
                'sale_ends_at' => $request->sale_ends_at,
                'stock_status' => $request->stock_status,
                'gender' => $request->gender,
                'color_name' => $request->color_name,
                'color_hex' => $request->color_hex,
                'material' => $request->material,
                'care_instructions' => $request->care_instructions,
                'weight' => $request->weight,
                'attributes' => $request->attributes,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords,
                'is_active' => $request->is_active ?? true,
                'is_featured' => $request->is_featured ?? false,
            ]);

            // Create product sizes
            if ($request->has('sizes')) {
                foreach ($request->sizes as $size) {
                    $product->sizes()->create([
                        'size' => $size['size'],
                        'sku' => $size['sku'] ?? $product->sku . '-' . $size['size'],
                        'price' => $size['price'] ?? null,
                        'compare_price' => $size['compare_price'] ?? null,
                        'cost' => $size['cost'] ?? null,
                        'stock' => $size['stock'] ?? 0,
                        'low_stock_threshold' => $size['low_stock_threshold'] ?? 5,
                        'is_active' => $size['is_active'] ?? true,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Product created successfully',
                'success' => true,
                'data' => new ProductResource($product->load(['category', 'sizes', 'images']))
            ], 201);
        });
    }

    /**
     * Update the specified product.
     *
     * @param UpdateProductRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateProductRequest $request, $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);

        return DB::transaction(function () use ($request, $product) {
            $product->update([
                'category_id' => $request->category_id ?? $product->category_id,
                'name' => $request->name ?? $product->name,
                'slug' => $request->name ? Str::slug($request->name) : $product->slug,
                'sku' => $request->sku ?? $product->sku,
                'description' => $request->description ?? $product->description,
                'short_description' => $request->short_description ?? $product->short_description,
                'base_price' => $request->base_price ?? $product->base_price,
                'sale_price' => $request->sale_price ?? $product->sale_price,
                'sale_starts_at' => $request->sale_starts_at ?? $product->sale_starts_at,
                'sale_ends_at' => $request->sale_ends_at ?? $product->sale_ends_at,
                'stock_status' => $request->stock_status ?? $product->stock_status,
                'gender' => $request->gender ?? $product->gender,
                'color_name' => $request->color_name ?? $product->color_name,
                'color_hex' => $request->color_hex ?? $product->color_hex,
                'material' => $request->material ?? $product->material,
                'care_instructions' => $request->care_instructions ?? $product->care_instructions,
                'weight' => $request->weight ?? $product->weight,
                'attributes' => $request->attributes ?? $product->attributes,
                'meta_title' => $request->meta_title ?? $product->meta_title,
                'meta_description' => $request->meta_description ?? $product->meta_description,
                'meta_keywords' => $request->meta_keywords ?? $product->meta_keywords,
                'is_active' => $request->is_active ?? $product->is_active,
                'is_featured' => $request->is_featured ?? $product->is_featured,
            ]);

            return response()->json([
                'message' => 'Product updated successfully',
                'success' => true,
                'data' => new ProductResource($product->load(['category', 'sizes', 'images']))
            ]);
        });
    }

    /**
     * Remove the specified product.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
            'success' => true
        ]);
    }

    /**
     * Bulk delete products.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorize('delete', Product::class);

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:products,id']
        ]);

        Product::whereIn('id', $request->ids)->delete();

        return response()->json([
            'message' => 'Products deleted successfully',
            'success' => true
        ]);
    }
}

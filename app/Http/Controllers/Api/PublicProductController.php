<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class PublicProductController extends Controller
{
    /**
     * Display a listing of products with filters.
     */
    public function index(Request $request)
    {
        try {
            $query = Product::with(['category', 'images', 'sizes'])->active();

            // Filter by category
            if ($request->has('category')) {
                $query->whereHas('category', function ($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            }

            // Search
            if ($request->has('search')) {
                $query->search($request->search);
            }

            // Price range
            if ($request->has('min_price') || $request->has('max_price')) {
                $query->priceBetween($request->get('min_price'), $request->get('max_price'));
            }

            // Sorting
            $sortBy = $request->get('sort', 'newest');
            $query->sortBy($sortBy);

            $perPage = (int)$request->get('per_page', 12);
            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'Products retrieved successfully'
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured products.
     */
    public function featured()
    {
        try {
            $products = Product::with(['category', 'images', 'sizes'])
                ->active()
                ->featured()
                ->limit(4)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'Featured products retrieved successfully'
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve featured products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get new arrivals.
     */
    public function newArrivals()
    {
        try {
            $products = Product::with(['category', 'images', 'sizes'])
                ->active()
                ->latest()
                ->limit(4)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'New arrivals retrieved successfully'
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve new arrivals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified product by slug.
     */
    public function show(string $slug)
    {
        try {
            $product = Product::with(['category', 'images', 'sizes'])
                ->active()
                ->where('slug', $slug)
                ->firstOrFail();

            // Related products (same category)
            $related = Product::with(['category', 'images', 'sizes'])
                ->active()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->limit(4)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'product' => $product,
                    'related' => $related
                ],
                'message' => 'Product retrieved successfully'
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}

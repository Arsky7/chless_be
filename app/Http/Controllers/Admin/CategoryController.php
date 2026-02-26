<?php
// app/Http/Controllers/Admin/CategoryController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * GET /admin/categories/stats
     * Summary counts for stat cards.
     */
    public function stats()
    {
        try {
            $total    = Category::count();
            $active   = Category::where('is_active', true)->count();
            $inactive = Category::where('is_active', false)->count();

            // Categories that have no products linked
            $empty = Category::doesntHave('products')->count();

            return response()->json([
                'success' => true,
                'data'    => [
                    'total'    => (int) $total,
                    'active'   => (int) $active,
                    'inactive' => (int) $inactive,
                    'empty'    => (int) $empty,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /admin/categories
     * All categories with product_count appended.
     */
    public function index()
    {
        try {
            $categories = Category::withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function ($cat) {
                    return [
                        'id'            => $cat->id,
                        'name'          => $cat->name,
                        'slug'          => $cat->slug,
                        'description'   => $cat->description,
                        'is_active'     => $cat->is_active,
                        'sort_order'    => $cat->sort_order,
                        'product_count' => $cat->products_count,
                        'created_at'    => $cat->created_at?->toDateString(),
                        'updated_at'    => $cat->updated_at?->toDateString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data'    => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /admin/categories
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'        => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active'   => 'boolean',
                'sort_order'  => 'integer|min:0',
            ]);

            $validated['slug'] = Str::slug($validated['name']);

            $category = Category::create($validated);

            return response()->json([
                'success' => true,
                'data'    => array_merge($category->toArray(), ['product_count' => 0]),
                'message' => 'Category created successfully',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /admin/categories/{category}
     */
    public function show(Category $category)
    {
        return response()->json([
            'success' => true,
            'data'    => array_merge($category->toArray(), ['product_count' => $category->products()->count()]),
        ]);
    }

    /**
     * PUT /admin/categories/{category}
     */
    public function update(Request $request, Category $category)
    {
        try {
            $validated = $request->validate([
                'name'        => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'is_active'   => 'boolean',
                'sort_order'  => 'integer|min:0',
            ]);

            if (isset($validated['name'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            $category->update($validated);
            $category->refresh();

            return response()->json([
                'success' => true,
                'data'    => array_merge($category->toArray(), ['product_count' => $category->products()->count()]),
                'message' => 'Category updated successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /admin/categories/{category}
     */
    public function destroy(Category $category)
    {
        try {
            if ($category->products()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with existing products. Move or delete products first.',
                ], 422);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
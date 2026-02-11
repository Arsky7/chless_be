<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Product\StoreCategoryRequest;
use App\Http\Requests\Api\Product\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->orderBy('name');

        if (!$request->boolean('show_inactive')) {
            $query->where('is_active', true);
        }

        $categories = $query->get();

        return response()->json([
    'success' => true,
    'data' => CategoryResource::collection($categories)
]);
    }

    /**
     * Store a newly created category.
     *
     * @param StoreCategoryRequest $request
     * @return JsonResponse
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'icon' => $request->icon,
            'image' => $request->image,
            'parent_id' => $request->parent_id,
            'order' => $request->order ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'success' => true,
            'data' => new CategoryResource($category->load('parent', 'children'))
        ], 201);
    }

    /**
     * Display the specified category.
     *
     * @param string $slug
     * @return JsonResponse
     */
    public function show($slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->with(['parent', 'children', 'products' => function($q) {
                $q->where('is_active', true)->limit(12);
            }])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category)
        ]);
    }

    /**
     * Update the specified category.
     *
     * @param UpdateCategoryRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateCategoryRequest $request, $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $this->authorize('update', $category);

        $category->update([
            'name' => $request->name ?? $category->name,
            'slug' => $request->name ? Str::slug($request->name) : $category->slug,
            'description' => $request->description ?? $category->description,
            'icon' => $request->icon ?? $category->icon,
            'image' => $request->image ?? $category->image,
            'parent_id' => $request->parent_id ?? $category->parent_id,
            'order' => $request->order ?? $category->order,
            'is_active' => $request->is_active ?? $category->is_active,
        ]);

        return response()->json([
            'message' => 'Category updated successfully',
            'success' => true,
            'data' => new CategoryResource($category->load('parent', 'children'))
        ]);
    }

    /**
     * Remove the specified category.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $this->authorize('delete', $category);

        // Check if category has products
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category that has products',
                'success' => false
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
            'success' => true
        ]);
    }
}

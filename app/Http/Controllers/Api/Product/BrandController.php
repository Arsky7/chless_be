<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Product\StoreBrandRequest;
use App\Http\Requests\Api\Product\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    /**
     * Display a listing of brands.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Brand::query();

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if (!$request->boolean('show_inactive')) {
            $query->where('is_active', true);
        }

        $brands = $query->orderBy('name')->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => BrandResource::collection($brands),
            'meta' => [
                'current_page' => $brands->currentPage(),
                'last_page' => $brands->lastPage(),
                'total' => $brands->total()
            ]
        ]);
    }

    /**
     * Store a newly created brand.
     *
     * @param StoreBrandRequest $request
     * @return JsonResponse
     */
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $this->authorize('create', Brand::class);

        $brand = Brand::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'logo' => $request->logo,
            'description' => $request->description,
            'website' => $request->website,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Brand created successfully',
            'success' => true,
            'data' => new BrandResource($brand)
        ], 201);
    }

    /**
     * Display the specified brand.
     *
     * @param string $slug
     * @return JsonResponse
     */
    public function show($slug): JsonResponse
    {
        $brand = Brand::where('slug', $slug)
            ->with(['products' => function($q) {
                $q->where('is_active', true)->limit(12);
            }])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new BrandResource($brand)
        ]);
    }

    /**
     * Update the specified brand.
     *
     * @param UpdateBrandRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateBrandRequest $request, $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);
        $this->authorize('update', $brand);

        $brand->update([
            'name' => $request->name ?? $brand->name,
            'slug' => $request->name ? Str::slug($request->name) : $brand->slug,
            'logo' => $request->logo ?? $brand->logo,
            'description' => $request->description ?? $brand->description,
            'website' => $request->website ?? $brand->website,
            'is_active' => $request->is_active ?? $brand->is_active,
        ]);

        return response()->json([
            'message' => 'Brand updated successfully',
            'success' => true,
            'data' => new BrandResource($brand)
        ]);
    }

    /**
     * Remove the specified brand.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $brand = Brand::findOrFail($id);
        $this->authorize('delete', $brand);

        // Check if brand has products
        if ($brand->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete brand that has products',
                'success' => false
            ], 422);
        }

        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully',
            'success' => true
        ]);
    }
}

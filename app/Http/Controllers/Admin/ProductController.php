<?php
// app/Http/Controllers/Admin/ProductController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Generate unique slug
     */
    private function generateUniqueSlug(string $name): string
    {
        // Generate base slug dari name
        $slug = Str::slug($name);
        
        // Jika slug kosong, beri default
        if (empty($slug)) {
            $slug = 'product-' . Str::random(5);
        }
        
        // Cek apakah slug sudah ada
        $originalSlug = $slug;
        $count = 1;
        
        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }
        
        return $slug;
    }

    /**
     * Generate SKU unik
     */
    private function generateUniqueSku(): string
    {
        $prefix = 'PRD';
        $random = strtoupper(Str::random(8));
        $sku = $prefix . '-' . $random;
        
        // Cek apakah SKU sudah ada
        while (Product::where('sku', $sku)->exists()) {
            $random = strtoupper(Str::random(8));
            $sku = $prefix . '-' . $random;
        }
        
        return $sku;
    }

    /**
     * Display a listing of products.
     */
    public function index(Request $request)
    {
        try {
            $query = Product::with('category')
                ->withCount('orderItems as sold_count');
            
            // Filter by category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            
            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            
            // Search by name or SKU
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%");
                });
            }
            
            // Sort
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);
            
            // Pagination
            $perPage = $request->get('per_page', 20);
            $products = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'Products retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            
            // Validasi input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:categories,id',
                'short_description' => 'nullable|string|max:500',
                'description' => 'nullable|string',
                'tags' => 'nullable|string',
                'is_featured' => 'boolean',
                'track_inventory' => 'boolean',
                'allow_backorders' => 'boolean',
                'is_returnable' => 'boolean',
                'is_active' => 'boolean',
                'visibility' => 'in:public,private',
                'base_price' => 'required|numeric|min:0',
                'sale_price' => 'nullable|numeric|min:0',
                'cost_price' => 'nullable|numeric|min:0',
                'stock_quantity' => 'nullable|integer|min:0',
                'low_stock_threshold' => 'nullable|integer|min:0',
                'weight' => 'nullable|numeric|min:0',
                'length' => 'nullable|numeric|min:0',
                'width' => 'nullable|numeric|min:0',
                'height' => 'nullable|numeric|min:0',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'meta_keywords' => 'nullable|string|max:500',
            ]);
            
            // Generate slug unik
            $validated['slug'] = $this->generateUniqueSlug($request->name);
            
            // Generate SKU jika tidak dikirim
            if (empty($request->sku)) {
                $validated['sku'] = $this->generateUniqueSku();
            } else {
                // Cek apakah SKU sudah ada
                if (Product::where('sku', $request->sku)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'SKU already exists',
                        'errors' => ['sku' => ['SKU sudah digunakan']]
                    ], 422);
                }
                $validated['sku'] = $request->sku;
            }
            
            // Set default values
            $validated['is_featured'] = $request->boolean('is_featured', false);
            $validated['track_inventory'] = $request->boolean('track_inventory', true);
            $validated['allow_backorders'] = $request->boolean('allow_backorders', false);
            $validated['is_returnable'] = $request->boolean('is_returnable', true);
            $validated['is_active'] = $request->boolean('is_active', true);
            $validated['visibility'] = $request->get('visibility', 'public');
            
            // Validasi sale price tidak lebih besar dari base price
            if (!empty($validated['sale_price']) && $validated['sale_price'] > $validated['base_price']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sale price cannot be greater than base price',
                    'errors' => ['sale_price' => ['Harga sale tidak boleh lebih besar dari harga base']]
                ], 422);
            }
            
            // Create product
            $product = Product::create($validated);
            
            // Handle tags (if you want to store as JSON or separate table)
            if (!empty($validated['tags'])) {
                $tags = array_map('trim', explode(',', $validated['tags']));
                // Simpan tags ke database sesuai kebutuhan
                // Contoh: $product->syncTags($tags);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->load('category')
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $product->load('category'),
                'message' => 'Product retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        try {
            DB::beginTransaction();
            
            // Validasi input
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'category_id' => 'sometimes|exists:categories,id',
                'short_description' => 'nullable|string|max:500',
                'description' => 'nullable|string',
                'tags' => 'nullable|string',
                'is_featured' => 'boolean',
                'track_inventory' => 'boolean',
                'allow_backorders' => 'boolean',
                'is_returnable' => 'boolean',
                'is_active' => 'boolean',
                'visibility' => 'in:public,private',
                'base_price' => 'sometimes|numeric|min:0',
                'sale_price' => 'nullable|numeric|min:0',
                'cost_price' => 'nullable|numeric|min:0',
                'stock_quantity' => 'nullable|integer|min:0',
                'low_stock_threshold' => 'nullable|integer|min:0',
                'weight' => 'nullable|numeric|min:0',
                'length' => 'nullable|numeric|min:0',
                'width' => 'nullable|numeric|min:0',
                'height' => 'nullable|numeric|min:0',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'meta_keywords' => 'nullable|string|max:500',
            ]);
            
            // Update slug if name changed
            if ($request->has('name') && $request->name !== $product->name) {
                $validated['slug'] = $this->generateUniqueSlug($request->name);
            }
            
            // Update SKU if provided and different
            if ($request->has('sku') && $request->sku !== $product->sku) {
                if (Product::where('sku', $request->sku)->where('id', '!=', $product->id)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'SKU already exists',
                        'errors' => ['sku' => ['SKU sudah digunakan']]
                    ], 422);
                }
                $validated['sku'] = $request->sku;
            }
            
            // Validate sale price
            $basePrice = $request->get('base_price', $product->base_price);
            $salePrice = $request->get('sale_price', $product->sale_price);
            
            if (!empty($salePrice) && $salePrice > $basePrice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sale price cannot be greater than base price',
                    'errors' => ['sale_price' => ['Harga sale tidak boleh lebih besar dari harga base']]
                ], 422);
            }
            
            // Update product
            $product->update($validated);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->fresh()->load('category')
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product)
    {
        try {
            DB::beginTransaction();
            
            // Cek apakah product bisa dihapus (misalnya tidak ada order)
            if ($product->orderItems()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete product that has been ordered'
                ], 422);
            }
            
            $product->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete products
     */
    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:products,id'
            ]);
            
            DB::beginTransaction();
            
            // Cek apakah ada produk yang sudah diorder
            $hasOrders = Product::whereIn('id', $request->ids)
                ->whereHas('orderItems')
                ->exists();
                
            if ($hasOrders) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some products have been ordered and cannot be deleted'
                ], 422);
            }
            
            Product::whereIn('id', $request->ids)->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Products deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update stock quantity
     */
    public function updateStock(Request $request, Product $product)
    {
        try {
            $request->validate([
                'stock_quantity' => 'required|integer|min:0',
                'reason' => 'nullable|string'
            ]);
            
            DB::beginTransaction();
            
            $oldStock = $product->stock_quantity;
            $product->stock_quantity = $request->stock_quantity;
            $product->save();
            
            // Log stock movement
            $product->stockMovements()->create([
                'old_quantity' => $oldStock,
                'new_quantity' => $request->stock_quantity,
                'reason' => $request->reason ?? 'Manual update',
                'user_id' => auth()->id()
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'data' => ['stock_quantity' => $product->stock_quantity]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(Product $product)
    {
        try {
            $product->is_featured = !$product->is_featured;
            $product->save();
            
            return response()->json([
                'success' => true,
                'message' => $product->is_featured ? 'Product featured' : 'Product unfeatured',
                'data' => ['is_featured' => $product->is_featured]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle featured',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle active status
     */
    public function toggleActive(Product $product)
    {
        try {
            $product->is_active = !$product->is_active;
            $product->save();
            
            return response()->json([
                'success' => true,
                'message' => $product->is_active ? 'Product activated' : 'Product deactivated',
                'data' => ['is_active' => $product->is_active]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle active',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate product
     */
    public function duplicate(Product $product)
    {
        try {
            DB::beginTransaction();
            
            $newProduct = $product->replicate();
            $newProduct->name = $product->name . ' (Copy)';
            $newProduct->slug = $this->generateUniqueSlug($product->name . '-copy');
            $newProduct->sku = $this->generateUniqueSku();
            $newProduct->created_at = now();
            $newProduct->updated_at = now();
            $newProduct->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Product duplicated successfully',
                'data' => $newProduct->load('category')
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if slug is available
     */
    public function checkSlug(Request $request)
    {
        try {
            $request->validate([
                'slug' => 'required|string',
                'exclude_id' => 'nullable|exists:products,id'
            ]);
            
            $query = Product::where('slug', $request->slug);
            
            if ($request->has('exclude_id')) {
                $query->where('id', '!=', $request->exclude_id);
            }
            
            $exists = $query->exists();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'available' => !$exists,
                    'slug' => $request->slug
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check slug',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
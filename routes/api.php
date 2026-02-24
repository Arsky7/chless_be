<?php
// routes/api.php - OPTIMIZED VERSION
// ============================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\DashboardController;

// Public test routes
Route::prefix('v1')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is working!',
            'timestamp' => now()->toDateTimeString(),
            'environment' => app()->environment()
        ]);
    });
});

// ============================================
// ADMIN ROUTES - Conditional Auth
// ============================================
$middleware = app()->environment('local') ? [] : ['auth:sanctum'];

Route::prefix('admin')->middleware($middleware)->group(function () {
    
    // ========================================
    // DASHBOARD ROUTES
    // ========================================
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);           // GET /admin/dashboard/stats
        Route::get('/recent', [DashboardController::class, 'recentActivities']); // GET /admin/dashboard/recent
    });
    
    // ========================================
    // REPORTS ROUTES
    // ========================================
    Route::prefix('reports')->group(function () {
        Route::get('/sales', [DashboardController::class, 'salesReport']);      // GET /admin/reports/sales?period=week
        Route::get('/products', [DashboardController::class, 'productReport']); // GET /admin/reports/products
        Route::get('/orders', [DashboardController::class, 'orderReport']);     // GET /admin/reports/orders
    });
    
    // ========================================
    // CATEGORY ROUTES (API Resource)
    // ========================================
    // Lebih ringkas pakai apiResource
    Route::apiResource('categories', CategoryController::class);
    
    // ========================================
    // PRODUCT ROUTES
    // ========================================
    Route::prefix('products')->group(function () {
        // CRUD
        Route::get('/', [ProductController::class, 'index']);          // GET /admin/products
        Route::post('/', [ProductController::class, 'store']);         // POST /admin/products
        Route::get('/{product}', [ProductController::class, 'show']);  // GET /admin/products/{id}
        Route::put('/{product}', [ProductController::class, 'update']); // PUT /admin/products/{id}
        Route::delete('/{product}', [ProductController::class, 'destroy']); // DELETE /admin/products/{id}
        
        // Bulk Operations
        Route::post('/bulk-delete', [ProductController::class, 'bulkDelete']); // POST /admin/products/bulk-delete
        Route::post('/bulk-update-status', [ProductController::class, 'bulkUpdateStatus']); // POST /admin/products/bulk-update-status
        
        // Single Product Operations
        Route::post('/{product}/duplicate', [ProductController::class, 'duplicate']); // POST /admin/products/{id}/duplicate
        Route::put('/{product}/stock', [ProductController::class, 'updateStock']); // PUT /admin/products/{id}/stock
        Route::patch('/{product}/toggle-featured', [ProductController::class, 'toggleFeatured']); // PATCH /admin/products/{id}/toggle-featured
        Route::patch('/{product}/toggle-active', [ProductController::class, 'toggleActive']); // PATCH /admin/products/{id}/toggle-active
        
        // Product Images
        Route::post('/{product}/images', [ProductController::class, 'uploadImages']); // POST /admin/products/{id}/images
        Route::delete('/{product}/images/{image}', [ProductController::class, 'deleteImage']); // DELETE /admin/products/{id}/images/{imageId}
        Route::patch('/{product}/images/{image}/main', [ProductController::class, 'setMainImage']); // PATCH /admin/products/{id}/images/{imageId}/main
    });
});

// OPTIONS route untuk CORS preflight
Route::options('/{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');
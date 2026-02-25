<?php
// routes/api.php - OPTIMIZED VERSION
// ============================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;

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
    // Category stats
    Route::get('categories/stats', [CategoryController::class, 'stats']);
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

    // ========================================
    // INVENTORY ROUTES
    // ========================================
    Route::prefix('inventory')->group(function () {
        Route::get('/stats', [InventoryController::class, 'stats']);                  // GET /admin/inventory/stats
        Route::get('/', [InventoryController::class, 'index']);                       // GET /admin/inventory
        Route::put('/{inventory}/restock', [InventoryController::class, 'restock']); // PUT /admin/inventory/{id}/restock
        Route::put('/{inventory}/adjust', [InventoryController::class, 'adjust']);   // PUT /admin/inventory/{id}/adjust
    });

    // ========================================
    // CUSTOMER ROUTES
    // ========================================
    Route::prefix('customers')->group(function () {
        Route::get('/stats', [\App\Http\Controllers\Admin\CustomerController::class, 'stats']);
        Route::get('/', [\App\Http\Controllers\Admin\CustomerController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\CustomerController::class, 'store']);
        Route::get('/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'show']);
        Route::put('/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'update']);
        Route::delete('/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'destroy']);
        Route::patch('/{customer}/toggle-active', [\App\Http\Controllers\Admin\CustomerController::class, 'toggleActive']);
    });
});

// OPTIONS route untuk CORS preflight
Route::options('/{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');
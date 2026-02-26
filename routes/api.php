<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\User\AddressController;
use App\Http\Controllers\Api\PublicProductController;
use App\Http\Controllers\Api\PublicCategoryController;
use App\Http\Controllers\Api\Admin\ReturnRequestController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\StaffController;
use App\Http\Controllers\Admin\CustomerController;

/* |-------------------------------------------------------------------------- | API Routes |-------------------------------------------------------------------------- */

Route::prefix('v1')->group(function () {

    // Auth Routes
    Route::post('/register', [RegisterController::class , 'register']);
    Route::post('/login', [LoginController::class , 'login']);

    Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [LoginController::class , 'logout']);
            Route::get('/me', function (Request $request) {
                    return response()->json([
                    'success' => true,
                    'data' => ['user' => new \App\Http\Resources\UserResource($request->user())]
                    ]);
                }
                );

                // User Profile
                Route::get('/profile', [ProfileController::class , 'show']);
                Route::put('/profile', [ProfileController::class , 'update']);
                Route::post('/profile/change-password', [ProfileController::class , 'changePassword']);
                Route::post('/profile/avatar', [ProfileController::class , 'uploadAvatar']);

                // Addresses
                Route::apiResource('addresses', AddressController::class);
                Route::patch('/addresses/{address}/set-default', [AddressController::class , 'setDefault']);
            }
            );

            Route::get('/ping', function () {
            return response()->json([
            'success' => true,
            'message' => 'API is working!',
            'timestamp' => now()->toDateTimeString(),
            ]);
        }
        );

        // Public Routes
        Route::get('/products', [PublicProductController::class , 'index']);
        Route::get('/products/featured', [PublicProductController::class , 'featured']);
        Route::get('/products/new-arrivals', [PublicProductController::class , 'newArrivals']);
        Route::get('/products/{slug}', [PublicProductController::class , 'show']);
        Route::get('/categories', [PublicCategoryController::class , 'index']);
    });

// Admin Routes
$middleware = app()->environment('local') ? [] : ['auth:sanctum'];

Route::prefix('admin')->middleware($middleware)->group(function () {

    // Dashboard & Reports
    Route::prefix('dashboard')->group(function () {
            Route::get('/stats', [DashboardController::class , 'stats']);
            Route::get('/recent', [DashboardController::class , 'recentActivities']);
        }
        );

        Route::prefix('reports')->group(function () {
            Route::get('/sales', [DashboardController::class , 'salesReport']);
            Route::get('/products', [DashboardController::class , 'productReport']);
            Route::get('/orders', [DashboardController::class , 'orderReport']);
        }
        );

        // Categories
        Route::get('categories/stats', [CategoryController::class , 'stats']);
        Route::apiResource('categories', CategoryController::class);

        // Products
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class , 'index']);
            Route::post('/', [ProductController::class , 'store']);
            Route::get('/{product}', [ProductController::class , 'show']);
            Route::put('/{product}', [ProductController::class , 'update']);
            Route::delete('/{product}', [ProductController::class , 'destroy']);
            Route::post('/bulk-delete', [ProductController::class , 'bulkDelete']);
            Route::post('/bulk-update-status', [ProductController::class , 'bulkUpdateStatus']);
            Route::post('/{product}/duplicate', [ProductController::class , 'duplicate']);
            Route::put('/{product}/stock', [ProductController::class , 'updateStock']);
            Route::patch('/{product}/toggle-featured', [ProductController::class , 'toggleFeatured']);
            Route::patch('/{product}/toggle-active', [ProductController::class , 'toggleActive']);
            Route::post('/{product}/images', [ProductController::class , 'uploadImages']);
            Route::delete('/{product}/images/{image}', [ProductController::class , 'deleteImage']);
            Route::patch('/{product}/images/{image}/main', [ProductController::class , 'setMainImage']);
        }
        );

        // Inventory
        Route::prefix('inventory')->group(function () {
            Route::get('/stats', [InventoryController::class , 'stats']);
            Route::get('/', [InventoryController::class , 'index']);
            Route::put('/{inventory}/restock', [InventoryController::class , 'restock']);
            Route::put('/{inventory}/adjust', [InventoryController::class , 'adjust']);
        }
        );

        // Customers
        Route::prefix('customers')->group(function () {
            Route::get('/stats', [CustomerController::class , 'stats']);
            Route::get('/', [CustomerController::class , 'index']);
            Route::post('/', [CustomerController::class , 'store']);
            Route::get('/{customer}', [CustomerController::class , 'show']);
            Route::put('/{customer}', [CustomerController::class , 'update']);
            Route::delete('/{customer}', [CustomerController::class , 'destroy']);
            Route::patch('/{customer}/toggle-active', [CustomerController::class , 'toggleActive']);
        }
        );

        // Returns
        Route::prefix('returns')->group(function () {
            Route::get('/stats', [ReturnRequestController::class , 'stats']);
            Route::get('/', [ReturnRequestController::class , 'index']);
            Route::post('/', [ReturnRequestController::class , 'store']);
            Route::get('/{returnRequest}', [ReturnRequestController::class , 'show']);
            Route::put('/{returnRequest}', [ReturnRequestController::class , 'update']);
            Route::post('/bulk-action', [ReturnRequestController::class , 'bulkAction']);
        }
        );

        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('/stats', [OrderController::class , 'stats']);
            Route::get('/', [OrderController::class , 'index']);
            Route::get('/{order}', [OrderController::class , 'show']);
            Route::patch('/{order}/status', [OrderController::class , 'updateStatus']);
            Route::delete('/{order}', [OrderController::class , 'destroy']);
        }
        );

        // Staff
        Route::prefix('staff')->group(function () {
            Route::get('/stats', [StaffController::class , 'stats']);
            Route::get('/', [StaffController::class , 'index']);
            Route::post('/', [StaffController::class , 'store']);
            Route::get('/{staff}', [StaffController::class , 'show']);
            Route::put('/{staff}', [StaffController::class , 'update']);
            Route::delete('/{staff}', [StaffController::class , 'destroy']);
            Route::patch('/{staff}/status', [StaffController::class , 'updateStatus']);
        }
        );
    });

// CORS Preflight
Route::options('/{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');
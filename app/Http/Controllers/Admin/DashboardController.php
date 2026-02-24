<?php
// app/Http/Controllers/Admin/DashboardController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function stats()
    {
        try {
            $today = now()->startOfDay();
            $yesterday = now()->subDay()->startOfDay();
            
            // Sales statistics - dengan pengecekan null
            $salesToday = Order::whereDate('created_at', $today)->sum('total') ?? 0;
            $salesYesterday = Order::whereDate('created_at', $yesterday)->sum('total') ?? 0;
            
            // Order statistics
            $ordersToday = Order::whereDate('created_at', $today)->count();
            $ordersYesterday = Order::whereDate('created_at', $yesterday)->count();
            
            // Customer statistics
            $customersToday = User::whereDate('created_at', $today)->count();
            $customersYesterday = User::whereDate('created_at', $yesterday)->count();
            
            // Product statistics
            $productsToday = Product::whereDate('created_at', $today)->count();
            $productsYesterday = Product::whereDate('created_at', $yesterday)->count();
            $totalProducts = Product::count();
            $activeProducts = Product::where('is_active', true)->count();
            
            // Stock statistics - dengan pengecekan relasi
            $lowStockCount = Product::whereHas('sizes', function ($q) {
                $q->where('stock', '>', 0)
                  ->where('stock', '<', 10);
            })->count();
            
            $outOfStockCount = Product::whereDoesntHave('sizes', function ($q) {
                $q->where('stock', '>', 0);
            })->orWhereHas('sizes', function ($q) {
                $q->where('stock', '<=', 0);
            })->count();
            
            // Top products - dengan pengecekan relasi orderItems
            $topProducts = Product::with(['category'])
                ->withCount(['orderItems as sold_count' => function ($q) {
                    $q->select(DB::raw('COALESCE(SUM(quantity), 0)'));
                }])
                ->orderByDesc('sold_count')
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => (float) $product->base_price,
                        'price_formatted' => 'Rp ' . number_format($product->base_price ?? 0, 0, ',', '.'),
                        'sold_count' => (int) ($product->sold_count ?? 0),
                        'category' => $product->category?->name ?? 'Uncategorized'
                    ];
                });

            // Recent orders
            $recentOrders = Order::with('user')
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number ?? 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                        'customer_name' => $order->user?->name ?? 'Guest',
                        'total' => (float) ($order->total ?? 0),
                        'total_formatted' => 'Rp ' . number_format($order->total ?? 0, 0, ',', '.'),
                        'status' => $order->status ?? 'pending',
                        'status_label' => $this->getStatusLabel($order->status ?? 'pending'),
                        'created_at' => $order->created_at ? $order->created_at->diffForHumans() : now()->diffForHumans(),
                    ];
                });

            // Weekly sales data
            $startOfWeek = now()->startOfWeek();
            $weeklySales = Order::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('DAYNAME(created_at) as day_name'),
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('COALESCE(SUM(total), 0) as total_sales')
                )
                ->where('created_at', '>=', $startOfWeek)
                ->groupBy('date', 'day_name')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'day' => $item->day_name,
                        'orders' => (int) $item->total_orders,
                        'sales' => (float) $item->total_sales
                    ];
                });

            $response = [
                'sales' => [
                    'today' => (float) $salesToday,
                    'yesterday' => (float) $salesYesterday,
                    'today_formatted' => 'Rp ' . number_format($salesToday, 0, ',', '.'),
                    'weekly' => $weeklySales
                ],
                'orders' => [
                    'today' => (int) $ordersToday,
                    'yesterday' => (int) $ordersYesterday,
                    'total' => (int) Order::count(),
                    'pending' => (int) Order::where('status', 'pending')->count(),
                    'processing' => (int) Order::where('status', 'processing')->count(),
                    'shipped' => (int) Order::where('status', 'shipped')->count(),
                    'delivered' => (int) Order::where('status', 'delivered')->count(),
                    'cancelled' => (int) Order::where('status', 'cancelled')->count(),
                ],
                'customers' => [
                    'today' => (int) $customersToday,
                    'yesterday' => (int) $customersYesterday,
                    'total' => (int) User::count()
                ],
                'products' => [
                    'today' => (int) $productsToday,
                    'yesterday' => (int) $productsYesterday,
                    'total' => (int) $totalProducts,
                    'active' => (int) $activeProducts,
                    'active_percentage' => $totalProducts > 0 
                        ? round(($activeProducts / $totalProducts) * 100, 1) 
                        : 0,
                    'low_stock' => (int) $lowStockCount,
                    'out_of_stock' => (int) $outOfStockCount,
                ],
                'top_products' => $topProducts,
                'recent_orders' => $recentOrders,
            ];

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            // Log error untuk debugging
            Log::error('Dashboard stats error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard statistics',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get sales report
     */
    public function salesReport(Request $request)
    {
        try {
            $period = $request->get('period', 'week');
            
            $data = match($period) {
                'week' => $this->getWeeklySales(),
                'month' => $this->getMonthlySales(),
                'year' => $this->getYearlySales(),
                default => $this->getWeeklySales()
            };

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Sales report error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load sales report'
            ], 500);
        }
    }

    /**
     * Get weekly sales data
     */
    private function getWeeklySales()
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();
        
        $sales = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('DAYNAME(created_at) as day_name'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(total), 0) as total_sales')
            )
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date', 'day_name')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->date,
                    'period_label' => $item->day_name,
                    'total_orders' => (int) $item->total_orders,
                    'total_sales' => (float) $item->total_sales,
                    'total_sales_formatted' => 'Rp ' . number_format($item->total_sales, 0, ',', '.')
                ];
            });

        return $sales;
    }

    /**
     * Get monthly sales data
     */
    private function getMonthlySales()
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        
        $sales = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('DAY(created_at) as day'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(total), 0) as total_sales')
            )
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date', 'day')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->date,
                    'period_label' => 'Day ' . $item->day,
                    'total_orders' => (int) $item->total_orders,
                    'total_sales' => (float) $item->total_sales,
                    'total_sales_formatted' => 'Rp ' . number_format($item->total_sales, 0, ',', '.')
                ];
            });

        return $sales;
    }

    /**
     * Get yearly sales data
     */
    private function getYearlySales()
    {
        $sales = Order::select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('MONTHNAME(created_at) as month_name'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(total), 0) as total_sales')
            )
            ->whereYear('created_at', now()->year)
            ->groupBy('month', 'month_name')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->month_name,
                    'period_label' => $item->month_name,
                    'total_orders' => (int) $item->total_orders,
                    'total_sales' => (float) $item->total_sales,
                    'total_sales_formatted' => 'Rp ' . number_format($item->total_sales, 0, ',', '.')
                ];
            });

        return $sales;
    }

    /**
     * Get status label
     */
    private function getStatusLabel($status)
    {
        return match($status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst($status)
        };
    }
}
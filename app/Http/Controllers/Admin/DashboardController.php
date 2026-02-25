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
            $todayStart = now()->startOfDay();
            $todayEnd = now()->endOfDay();
            $yesterdayStart = now()->subDay()->startOfDay();
            $yesterdayEnd = now()->subDay()->endOfDay();
            
            // Stats summary using a single query where possible or optimized indexes
            $salesToday = Order::whereBetween('created_at', [$todayStart, $todayEnd])->sum('total') ?? 0;
            $salesYesterday = Order::whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->sum('total') ?? 0;
            
            $ordersToday = Order::whereBetween('created_at', [$todayStart, $todayEnd])->count();
            $ordersYesterday = Order::whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
            
            $customersToday = User::whereBetween('created_at', [$todayStart, $todayEnd])->count();
            $customersYesterday = User::whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
            
            $productsToday = Product::whereBetween('created_at', [$todayStart, $todayEnd])->count();
            $productsYesterday = Product::whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
            
            $totalProducts = Product::count();
            $activeProducts = Product::where('is_active', true)->count();
            
            // Stock statistics - Optimized
            $lowStockCount = Product::whereHas('sizes', function ($q) {
                $q->where('stock', '>', 0)->where('stock', '<', 10);
            })->count();
            
            $outOfStockCount = Product::whereDoesntHave('sizes', function ($q) {
                $q->where('stock', '>', 0);
            })->count();
            
            // Top products - Eager loaded
            $topProducts = Product::with(['category'])
                ->withCount(['orderItems as sold_count' => function ($q) {
                    $q->select(DB::raw('COALESCE(SUM(quantity), 0)'));
                }])
                ->orderByDesc('sold_count')
                ->limit(5)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->base_price,
                    'price_formatted' => 'Rp ' . number_format($p->base_price ?? 0, 0, ',', '.'),
                    'sold_count' => (int) ($p->sold_count ?? 0),
                    'category' => $p->category?->name ?? 'Uncategorized'
                ]);

            // Recent orders - Eager loaded
            $recentOrders = Order::with('user')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn($o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number ?? 'ORD-' . str_pad($o->id, 6, '0', STR_PAD_LEFT),
                    'customer_name' => $o->user?->name ?? 'Guest',
                    'total' => (float) ($o->total ?? 0),
                    'total_formatted' => 'Rp ' . number_format($o->total ?? 0, 0, ',', '.'),
                    'status' => $o->status ?? 'pending',
                    'status_label' => $this->getStatusLabel($o->status ?? 'pending'),
                    'created_at' => $o->created_at?->diffForHumans() ?? now()->diffForHumans(),
                ]);

            $response = [
                'sales' => [
                    'today' => (float) $salesToday,
                    'yesterday' => (float) $salesYesterday,
                    'today_formatted' => 'Rp ' . number_format($salesToday, 0, ',', '.'),
                ],
                'orders' => [
                    'today' => (int) $ordersToday,
                    'yesterday' => (int) $ordersYesterday,
                    'total' => (int) Order::count(),
                    'pending' => (int) Order::where('status', 'pending')->count(),
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
                    'active_percentage' => $totalProducts > 0 ? round(($activeProducts / $totalProducts) * 100, 1) : 0,
                    'low_stock' => (int) $lowStockCount,
                    'out_of_stock' => (int) $outOfStockCount,
                ],
                'top_products' => $topProducts,
                'recent_orders' => $recentOrders,
            ];

            return response()->json(['success' => true, 'data' => $response]);
        } catch (\Exception $e) {
            Log::error('Dashboard stats error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load dashboard statistics'], 500);
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
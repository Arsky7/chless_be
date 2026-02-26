<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\ReturnRequest;
use App\Models\WarehouseStock;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get admin dashboard statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $week = now()->startOfWeek();
        $lastWeek = now()->subWeek()->startOfWeek();
        $month = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Sales statistics with comparison
        $todaySales = Order::whereDate('created_at', $today)->sum('total_amount');
        $yesterdaySales = Order::whereDate('created_at', $yesterday)->sum('total_amount');
        
        $sales = [
            'today' => $todaySales,
            'yesterday' => $yesterdaySales,
            'week' => Order::where('created_at', '>=', $week)->sum('total_amount'),
            'last_week' => Order::whereBetween('created_at', [$lastWeek, $week])->sum('total_amount'),
            'month' => Order::where('created_at', '>=', $month)->sum('total_amount'),
            'last_month' => Order::whereBetween('created_at', [$lastMonth, $month])->sum('total_amount'),
            'total' => Order::sum('total_amount'),
            'today_formatted' => 'Rp ' . number_format($todaySales, 0, ',', '.'),
            'yesterday_formatted' => 'Rp ' . number_format($yesterdaySales, 0, ',', '.'),
            'week_formatted' => 'Rp ' . number_format(Order::where('created_at', '>=', $week)->sum('total_amount'), 0, ',', '.'),
            'month_formatted' => 'Rp ' . number_format(Order::where('created_at', '>=', $month)->sum('total_amount'), 0, ',', '.'),
            'total_formatted' => 'Rp ' . number_format(Order::sum('total_amount'), 0, ',', '.'),
        ];

        // Order statistics with comparison
        $todayOrders = Order::whereDate('created_at', $today)->count();
        $yesterdayOrders = Order::whereDate('created_at', $yesterday)->count();

        $orders = [
            'today' => $todayOrders,
            'yesterday' => $yesterdayOrders,
            'week' => Order::where('created_at', '>=', $week)->count(),
            'last_week' => Order::whereBetween('created_at', [$lastWeek, $week])->count(),
            'month' => Order::where('created_at', '>=', $month)->count(),
            'last_month' => Order::whereBetween('created_at', [$lastMonth, $month])->count(),
            'pending' => Order::where('status', 'pending')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'shipped' => Order::where('status', 'shipped')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'total' => Order::count(),
        ];

        // Customer statistics with comparison
        $todayCustomers = User::whereDate('created_at', $today)->where('type', 'customer')->count();
        $yesterdayCustomers = User::whereDate('created_at', $yesterday)->where('type', 'customer')->count();

        $customers = [
            'today' => $todayCustomers,
            'yesterday' => $yesterdayCustomers,
            'week' => User::where('created_at', '>=', $week)->where('type', 'customer')->count(),
            'last_week' => User::whereBetween('created_at', [$lastWeek, $week])->where('type', 'customer')->count(),
            'month' => User::where('created_at', '>=', $month)->where('type', 'customer')->count(),
            'last_month' => User::whereBetween('created_at', [$lastMonth, $month])->where('type', 'customer')->count(),
            'total' => User::where('type', 'customer')->count(),
            'active' => User::where('type', 'customer')->where('is_active', true)->count(),
        ];

        // Product statistics with comparison
        $todayProducts = Product::whereDate('created_at', $today)->count();
        $yesterdayProducts = Product::whereDate('created_at', $yesterday)->count();

        $products = [
            'today' => $todayProducts,
            'yesterday' => $yesterdayProducts,
            'week' => Product::where('created_at', '>=', $week)->count(),
            'last_week' => Product::whereBetween('created_at', [$lastWeek, $week])->count(),
            'month' => Product::where('created_at', '>=', $month)->count(),
            'last_month' => Product::whereBetween('created_at', [$lastMonth, $month])->count(),
            'total' => Product::count(),
            'active' => Product::where('is_active', true)->count(),
            'out_of_stock' => Product::where('stock_status', 'out_of_stock')->count(),
            'low_stock' => WarehouseStock::lowStock()->count(),
            'featured' => Product::where('is_featured', true)->count(),
        ];

        // Return statistics
        $returns = [
            'pending' => ReturnRequest::where('status', 'pending')->count(),
            'approved' => ReturnRequest::where('status', 'approved')->count(),
            'rejected' => ReturnRequest::where('status', 'rejected')->count(),
            'completed' => ReturnRequest::where('status', 'completed')->count(),
            'total' => ReturnRequest::count(),
        ];

        // Recent orders
        $recentOrders = Order::with(['user', 'items.productSize.product'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->user->name,
                    'total' => $order->total_amount,
                    'total_formatted' => 'Rp ' . number_format($order->total_amount, 0, ',', '.'),
                    'status' => $order->status,
                    'status_label' => $order->status_label ?? ucfirst($order->status),
                    'created_at' => $order->created_at->format('d M Y H:i'),
                ];
            });

        // Top products
        $topProducts = Product::with(['category'])
            ->orderBy('sold_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'sold_count' => $product->sold_count,
                    'price' => $product->base_price,
                    'price_formatted' => 'Rp ' . number_format($product->base_price, 0, ',', '.'),
                    'stock_status' => $product->stock_status,
                ];
            });

        // Sales chart data (last 7 days)
        $salesChart = collect(range(6, 0))->map(function($days) {
            $date = now()->subDays($days);
            return [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('D'),
                'sales' => (float) Order::whereDate('created_at', $date)->sum('total_amount'),
                'orders' => (int) Order::whereDate('created_at', $date)->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => $sales,
                'orders' => $orders,
                'customers' => $customers,
                'products' => $products,
                'returns' => $returns,
                'recent_orders' => $recentOrders,
                'top_products' => $topProducts,
                'sales_chart' => $salesChart,
            ]
        ]);
    }

    /**
     * Get sales report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function salesReport(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly,yearly',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date'
        ]);

        $period = $request->period ?? 'monthly';
        $fromDate = $request->from_date ? now()->parse($request->from_date) : now()->startOfMonth();
        $toDate = $request->to_date ? now()->parse($request->to_date) : now();

        $query = Order::whereBetween('created_at', [$fromDate, $toDate])
            ->where('payment_status', 'paid');

        $groupBy = match($period) {
            'daily' => DB::raw('DATE(created_at) as period'),
            'weekly' => DB::raw('YEARWEEK(created_at) as period'),
            'monthly' => DB::raw('DATE_FORMAT(created_at, "%Y-%m") as period'),
            'yearly' => DB::raw('YEAR(created_at) as period'),
            default => DB::raw('DATE_FORMAT(created_at, "%Y-%m") as period')
        };

        $sales = $query->select(
                $groupBy,
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('AVG(total_amount) as average_order_value')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function($item) use ($period) {
                if ($period === 'weekly') {
                    $year = substr($item->period, 0, 4);
                    $week = substr($item->period, 4);
                    $item->period_label = "Week {$week}, {$year}";
                } elseif ($period === 'monthly') {
                    $item->period_label = \Carbon\Carbon::createFromFormat('Y-m', $item->period)->format('F Y');
                } else {
                    $item->period_label = $item->period;
                }
                return $item;
            });

        $totalItems = OrderItem::whereHas('order', function($q) use ($fromDate, $toDate) {
                $q->whereBetween('created_at', [$fromDate, $toDate])
                  ->where('payment_status', 'paid');
            })
            ->sum('quantity');

        $summary = [
            'total_orders' => $query->count(),
            'total_sales' => $query->sum('total_amount'),
            'average_order' => $query->avg('total_amount'),
            'total_items' => $totalItems,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'from_date' => $fromDate->format('Y-m-d'),
                'to_date' => $toDate->format('Y-m-d'),
                'summary' => [
                    'total_orders' => $summary['total_orders'],
                    'total_sales' => $summary['total_sales'],
                    'total_sales_formatted' => 'Rp ' . number_format($summary['total_sales'], 0, ',', '.'),
                    'average_order' => $summary['average_order'] ?? 0,
                    'average_order_formatted' => 'Rp ' . number_format($summary['average_order'] ?? 0, 0, ',', '.'),
                    'total_items' => $summary['total_items'],
                ],
                'sales_data' => $sales,
            ]
        ]);
    }

    /**
     * Get product report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function productReport(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'category_id' => 'nullable|exists:categories,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $fromDate = $request->from_date ? now()->parse($request->from_date) : now()->startOfMonth();
        $toDate = $request->to_date ? now()->parse($request->to_date) : now();
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);

        $query = Product::with(['category']);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }



        $products = $query->get()->map(function($product) use ($fromDate, $toDate) {
            $soldQuantity = OrderItem::whereHas('productSize', function($q) use ($product) {
                    $q->where('product_id', $product->id);
                })
                ->whereHas('order', function($q) use ($fromDate, $toDate) {
                    $q->whereBetween('created_at', [$fromDate, $toDate])
                      ->where('payment_status', 'paid');
                })
                ->sum('quantity');

            $revenue = OrderItem::whereHas('productSize', function($q) use ($product) {
                    $q->where('product_id', $product->id);
                })
                ->whereHas('order', function($q) use ($fromDate, $toDate) {
                    $q->whereBetween('created_at', [$fromDate, $toDate])
                      ->where('payment_status', 'paid');
                })
                ->sum(DB::raw('quantity * price'));

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'sold_quantity' => (int) $soldQuantity,
                'total_revenue' => (float) $revenue,
                'total_revenue_formatted' => 'Rp ' . number_format($revenue, 0, ',', '.'),
                'current_stock' => $product->stock_quantity,
            ];
        })
        ->filter(fn($item) => $item['sold_quantity'] > 0)
        ->sortByDesc('sold_quantity')
        ->values();

        $total = $products->count();
        $pagedData = $products->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $pagedData,
                'meta' => [
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage),
                    'total' => $total,
                    'per_page' => $perPage,
                    'from_date' => $fromDate->format('Y-m-d'),
                    'to_date' => $toDate->format('Y-m-d'),
                ],
            ],
        ]);
    }
}
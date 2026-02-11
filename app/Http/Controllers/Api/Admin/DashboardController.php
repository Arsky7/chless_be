<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
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
        $week = now()->startOfWeek();
        $month = now()->startOfMonth();

        // Sales statistics
        $sales = [
            'today' => Order::whereDate('created_at', $today)->sum('total_amount'),
            'week' => Order::where('created_at', '>=', $week)->sum('total_amount'),
            'month' => Order::where('created_at', '>=', $month)->sum('total_amount'),
            'total' => Order::sum('total_amount')
        ];

        // Order statistics
        $orders = [
            'today' => Order::whereDate('created_at', $today)->count(),
            'pending' => Order::where('status', 'pending')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'shipped' => Order::where('status', 'shipped')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'total' => Order::count()
        ];

        // Customer statistics
        $customers = [
            'today' => User::whereDate('created_at', $today)->where('type', 'customer')->count(),
            'week' => User::where('created_at', '>=', $week)->where('type', 'customer')->count(),
            'month' => User::where('created_at', '>=', $month)->where('type', 'customer')->count(),
            'total' => User::where('type', 'customer')->count(),
            'active' => User::where('type', 'customer')->where('is_active', true)->count()
        ];

        // Product statistics
        $products = [
            'total' => Product::count(),
            'active' => Product::where('is_active', true)->count(),
            'out_of_stock' => Product::where('stock_status', 'out_of_stock')->count(),
            'low_stock' => WarehouseStock::lowStock()->count(),
            'featured' => Product::where('is_featured', true)->count()
        ];

        // Return statistics
        $returns = [
            'pending' => ReturnRequest::where('status', 'pending')->count(),
            'approved' => ReturnRequest::where('status', 'approved')->count(),
            'rejected' => ReturnRequest::where('status', 'rejected')->count(),
            'completed' => ReturnRequest::where('status', 'completed')->count(),
            'total' => ReturnRequest::count()
        ];

        // Recent orders
        $recentOrders = Order::with(['user', 'items.productSize.product'])
            ->latest()
            ->limit(10)
            ->get();

        // Top products
        $topProducts = Product::with(['category', 'brand'])
            ->orderBy('sold_count', 'desc')
            ->limit(10)
            ->get();

        // Sales chart data (last 7 days)
        $salesChart = collect(range(6, 0))->map(function($days) {
            $date = now()->subDays($days);
            return [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('D'),
                'sales' => Order::whereDate('created_at', $date)->sum('total_amount'),
                'orders' => Order::whereDate('created_at', $date)->count()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => [
                    'today' => $sales['today'],
                    'week' => $sales['week'],
                    'month' => $sales['month'],
                    'total' => $sales['total'],
                    'today_formatted' => 'Rp ' . number_format($sales['today'], 0, ',', '.'),
                    'week_formatted' => 'Rp ' . number_format($sales['week'], 0, ',', '.'),
                    'month_formatted' => 'Rp ' . number_format($sales['month'], 0, ',', '.'),
                    'total_formatted' => 'Rp ' . number_format($sales['total'], 0, ',', '.')
                ],
                'orders' => $orders,
                'customers' => $customers,
                'products' => $products,
                'returns' => $returns,
                'recent_orders' => $recentOrders->map(function($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->user->name,
                        'total' => $order->total_amount,
                        'total_formatted' => 'Rp ' . number_format($order->total_amount, 0, ',', '.'),
                        'status' => $order->status,
                        'status_label' => $order->status_label ?? ucfirst($order->status),
                        'created_at' => $order->created_at->format('d M Y H:i')
                    ];
                }),
                'top_products' => $topProducts->map(function($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'sold_count' => $product->sold_count,
                        'price' => $product->base_price,
                        'price_formatted' => 'Rp ' . number_format($product->base_price, 0, ',', '.'),
                        'stock_status' => $product->stock_status
                    ];
                }),
                'sales_chart' => $salesChart
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

        // ✅ FIX #1: Gunakan DB::raw dengan alias langsung
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
            ->get();

        // Hitung total items
        $totalItems = OrderItem::whereHas('order', function($q) use ($fromDate, $toDate) {
                $q->whereBetween('created_at', [$fromDate, $toDate])
                  ->where('payment_status', 'paid');
            })
            ->sum('quantity');

        $summary = [
            'total_orders' => $query->count(),
            'total_sales' => $query->sum('total_amount'),
            'average_order' => $query->avg('total_amount'),
            'total_items' => $totalItems
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
                    'total_items' => $summary['total_items']
                ],
                'sales_data' => $sales
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
            'brand_id' => 'nullable|exists:brands,id'
        ]);

        $fromDate = $request->from_date ? now()->parse($request->from_date) : now()->startOfMonth();
        $toDate = $request->to_date ? now()->parse($request->to_date) : now();

        $query = Product::query();

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->brand_id) {
            $query->where('brand_id', $request->brand_id);
        }

        // ✅ FIX #2: Hitung revenue dengan subquery manual
        $products = $query->with(['category', 'brand'])
            ->withCount(['orderItems as sold_quantity' => function($q) use ($fromDate, $toDate) {
                $q->whereHas('order', function($q2) use ($fromDate, $toDate) {
                    $q2->whereBetween('created_at', [$fromDate, $toDate])
                       ->where('payment_status', 'paid');
                });
            }])
            ->get()
            ->map(function($product) use ($fromDate, $toDate) {
                // Hitung revenue manual
                $revenue = OrderItem::whereHas('productSize', function($q) use ($product) {
                        $q->where('product_id', $product->id);
                    })
                    ->whereHas('order', function($q) use ($fromDate, $toDate) {
                        $q->whereBetween('created_at', [$fromDate, $toDate])
                          ->where('payment_status', 'paid');
                    })
                    ->sum(DB::raw('quantity * price'));
                
                $product->total_revenue = $revenue;
                return $product;
            })
            ->sortByDesc('sold_quantity')
            ->values();

        // Pagination manual
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);
        $pagedData = $products->slice(($currentPage - 1) * $perPage, $perPage)->values();
        
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $pagedData,
            $products->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $paginated->map(function($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'category' => $product->category?->name,
                        'brand' => $product->brand?->name,
                        'sold_quantity' => (int) $product->sold_quantity,
                        'total_revenue' => (float) $product->total_revenue,
                        'total_revenue_formatted' => 'Rp ' . number_format($product->total_revenue ?? 0, 0, ',', '.'),
                        'current_stock' => $product->stock_quantity
                    ];
                }),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'from_date' => $fromDate->format('Y-m-d'),
                    'to_date' => $toDate->format('Y-m-d')
                ]
            ]
        ]);
    }
}
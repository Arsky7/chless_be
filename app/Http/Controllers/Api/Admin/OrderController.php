<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->latest()->paginate($request->get('per_page', 10));
        return OrderResource::collection($orders);
    }

    public function stats()
    {
        $today = now()->startOfDay();
        return response()->json([
            'stats' => [
                'total_orders' => Order::count(),
                'pending_today' => Order::where('status', 'pending')->where('created_at', '>=', $today)->count(),
                'completed_today' => Order::where('status', 'completed')->where('created_at', '>=', $today)->count(),
                'total_revenue' => (float) Order::where('payment_status', 'paid')->sum('total'),
            ],
            'summary' => [
                'new_orders' => Order::where('created_at', '>=', $today)->count(),
                'total_revenue' => (float) Order::where('payment_status', 'paid')->where('created_at', '>=', $today)->sum('total'),
                'total_revenue_formatted' => 'IDR ' . number_format(Order::where('payment_status', 'paid')->where('created_at', '>=', $today)->sum('total'), 0, ',', '.'),
                'growth' => 10,
                'is_positive' => true
            ]
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['user', 'items.product', 'items.variant']);
        return new OrderResource($order);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate(['status' => 'required|string']);
        $order->update(['status' => $request->status]);
        return new OrderResource($order);
    }

    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:orders,id',
            'status' => 'required|string'
        ]);
        Order::whereIn('id', $request->ids)->update(['status' => $request->status]);
        return response()->json(['message' => 'Orders updated successfully']);
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json(['message' => 'Order deleted successfully']);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    /**
     * Get list of shipments.
     */
    public function index(Request $request)
    {
        // Only fetch orders that are in a shipping-related status or have shipping set up.
        $query = Order::with(['user', 'items.product', 'items.variant'])
            ->whereIn('status', ['processing', 'shipped', 'delivered', 'completed']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($qu) use ($search) {
                        $qu->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Ready to ship (processing mostly)
        if ($request->has('ready_to_ship') && $request->ready_to_ship == 'true') {
            $query->where('status', 'processing')->whereNull('tracking_number');
        }

        $shipments = $query->latest()->paginate($request->get('per_page', 10));

        return OrderResource::collection($shipments);
    }

    /**
     * Get shipping statistics.
     */
    public function stats()
    {
        return response()->json([
            'ready_to_ship' => Order::where('status', 'processing')->count(),
            'shipped' => Order::where('status', 'shipped')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'total_shipping_cost' => (float) Order::where('payment_status', 'paid')->sum('shipping_cost')
        ]);
    }

    /**
     * Update tracking number and mark as shipped.
     */
    public function updateTracking(Request $request, Order $order)
    {
        $request->validate([
            'tracking_number' => 'required|string|max:255',
            'courier' => 'nullable|string|max:100', // Optional info if needed
        ]);

        $order->update([
            'tracking_number' => $request->tracking_number,
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);

        return new OrderResource($order->fresh(['user', 'items.product', 'items.variant']));
    }

    /**
     * Mark order as delivered.
     */
    public function markDelivered(Order $order)
    {
        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        return new OrderResource($order->fresh(['user', 'items.product', 'items.variant']));
    }
}

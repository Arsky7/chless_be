<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    /**
     * Get payment statistics based on orders.
     */
    public function stats(): JsonResponse
    {
        $totalRevenue = (float) Order::where('payment_status', 'paid')->sum('total');

        $totalOrders    = Order::count();
        $successfulCount = Order::where('payment_status', 'paid')->count();
        $pendingCount   = Order::where('payment_status', 'pending')->count();
        $failedCount    = Order::whereIn('payment_status', ['failed', 'cancelled'])->count();
        $refundedCount  = Order::where('payment_status', 'refunded')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue'       => $totalRevenue,
                'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                'total'               => $totalOrders,
                'successful'          => $successfulCount,
                'pending'             => $pendingCount,
                'failed'              => $failedCount,
                'refunded'            => $refundedCount,
            ],
        ]);
    }

    /**
     * List payments (orders) with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with('user')->latest();

        // Filter by payment status
        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'success') {
                $query->where('payment_status', 'paid');
            } elseif ($request->status === 'failed') {
                $query->whereIn('payment_status', ['failed', 'cancelled']);
            } else {
                $query->where('payment_status', $request->status);
            }
        }

        // Filter by payment method
        if ($request->filled('method') && $request->method !== 'all') {
            $query->where('payment_method', $request->method);
        }

        // Filter by date range
        if ($request->filled('date')) {
            $date = $request->date;
            if ($date === 'today') {
                $query->whereDate('created_at', now()->toDateString());
            } elseif ($date === 'yesterday') {
                $query->whereDate('created_at', now()->subDay()->toDateString());
            } elseif ($date === 'week') {
                $query->where('created_at', '>=', now()->startOfWeek());
            } elseif ($date === 'month') {
                $query->where('created_at', '>=', now()->startOfMonth());
            }
        }

        // Search by order number or customer name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $payments = $query->paginate($perPage);

        $data = $payments->map(function (Order $order) {
            $user = $order->user;
            $name  = $user ? $user->name : 'Guest';
            $email = $user ? $user->email : '';
            $initial = strtoupper(substr($name, 0, 1));

            // Map payment_status to frontend-expected values
            $statusMap = [
                'paid'      => 'success',
                'pending'   => 'pending',
                'failed'    => 'failed',
                'cancelled' => 'failed',
                'refunded'  => 'refunded',
            ];

            return [
                'id'          => 'PAY-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'orderId'     => $order->order_number,
                'orderAmount' => (float) $order->total,
                'customer'    => [
                    'name'    => $name,
                    'email'   => $email,
                    'initial' => $initial,
                ],
                'method'      => [
                    'type' => $this->mapMethodType($order->payment_method),
                    'name' => $this->mapMethodName($order->payment_method),
                    'logo' => strtolower($order->payment_method ?? 'bank'),
                ],
                'amount'      => (float) $order->total,
                'date'        => $order->created_at->format('Y-m-d H:i'),
                'status'      => $statusMap[$order->payment_status] ?? 'pending',
                'reference'   => 'TRX-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'current_page' => $payments->currentPage(),
                'last_page'    => $payments->lastPage(),
                'per_page'     => $payments->perPage(),
                'total'        => $payments->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function mapMethodType(?string $method): string
    {
        if (!$method) return 'bank';
        $method = strtolower($method);
        if (in_array($method, ['gopay', 'ovo', 'dana', 'shopeepay', 'linkaja'])) return 'ewallet';
        if (in_array($method, ['visa', 'mastercard', 'credit_card'])) return 'card';
        if ($method === 'cod') return 'cod';
        return 'bank';
    }

    private function mapMethodName(?string $method): string
    {
        if (!$method) return 'Bank Transfer';
        $names = [
            'bca'        => 'Bank BCA',
            'bni'        => 'Bank BNI',
            'bri'        => 'Bank BRI',
            'mandiri'    => 'Bank Mandiri',
            'gopay'      => 'GoPay',
            'ovo'        => 'OVO',
            'dana'       => 'DANA',
            'shopeepay'  => 'ShopeePay',
            'linkaja'    => 'LinkAja',
            'visa'       => 'Visa',
            'mastercard' => 'Mastercard',
            'credit_card' => 'Credit Card',
            'cod'        => 'Cash on Delivery',
        ];
        return $names[strtolower($method)] ?? ucfirst($method);
    }
}

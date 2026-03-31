<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;

class CheckoutController extends Controller
{
    public function __construct()
    {
        // Set Midtrans configuration
        Config::$serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY'));
        Config::$isProduction = config('services.midtrans.is_production', env('MIDTRANS_IS_PRODUCTION', false));
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Process checkout and get Snap Token
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.size' => 'required|string',
            'customer_name' => 'required|string',
            'customer_phone' => 'required|string',
            'customer_email' => 'nullable|email',
            'country' => 'required|string',
            'sub_district_city' => 'required|string',
            'address_details' => 'required|string',
            'notes' => 'nullable|string',
            'district' => 'required|string',
            'postal_code' => 'required|string',
            'shipping_cost' => 'nullable|numeric',
            'shipping_method' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Get authenticated user if any
            $user = auth('sanctum')->user();

            // Calculate totals and prepare items
            $totalAmount = 0;
            $orderItems = [];
            $itemDetails = [];

            foreach ($request->items as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $price = $product->sale_price ?? $product->base_price;
                
                $subtotal = $price * $itemData['quantity'];
                $totalAmount += $subtotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $itemData['quantity'],
                    'price' => $price,
                    'subtotal' => $subtotal,
                    'size' => $itemData['size'],
                ];

                $itemDetails[] = [
                    'id' => $product->id,
                    'price' => (int) $price,
                    'quantity' => (int) $itemData['quantity'],
                    'name' => mb_strimwidth($product->name, 0, 50, '...'), 
                ];
            }

            // Use shipping cost from RajaOngkir (sent from frontend)
            $shippingCost = (int) ($request->shipping_cost ?? 25000);
            $totalAmount += $shippingCost;
            $itemDetails[] = [
                'id' => 'SHIPPING',
                'price' => $shippingCost,
                'quantity' => 1,
                'name' => $request->shipping_method ?? 'Shipping Cost'
            ];

            // Create safe email for guest checkout
            $checkoutEmail = $request->customer_email ?: 'guest@' . strtolower(str_replace(' ', '', $request->customer_name)) . '.com';
            
            // Build full address
            $fullAddress = "{$request->address_details}, {$request->district}, {$request->sub_district_city}, {$request->country}, {$request->postal_code}";

            // Create Order
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());
            
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user ? $user->id : null, 
                'total' => $totalAmount,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'shipping_address' => $fullAddress,
                'shipping_cost' => $shippingCost,
                'notes' => $request->notes,
            ]);

            // Save Order Items
            foreach ($orderItems as $item) {
                $order->items()->create($item);
            }

            // Create Midtrans Transaction Payload
            $payload = [
                'transaction_details' => [
                    'order_id' => $order->order_number,
                    'gross_amount' => (int) $totalAmount,
                ],
                'customer_details' => [
                    'first_name' => $request->customer_name,
                    'email' => $checkoutEmail,
                    'phone' => $request->customer_phone,
                    'billing_address' => [
                        'first_name' => $request->customer_name,
                        'phone' => $request->customer_phone,
                        'address' => $fullAddress,
                    ],
                    'shipping_address' => [
                        'first_name' => $request->customer_name,
                        'phone' => $request->customer_phone,
                        'address' => $fullAddress,
                    ],
                ],
                'item_details' => $itemDetails,
            ];

            // Get Snap Token
            $snapToken = Snap::getSnapToken($payload);

            // Save Token to Order
            $order->snap_token = $snapToken;
            $order->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'snap_token' => $snapToken,
                'order' => $order
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process checkout.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Midtrans Payment Notification (Webhook)
     */
    public function notification(Request $request)
    {
        try {
            $notification = new Notification();
            
            $transaction = $notification->transaction_status;
            $type = $notification->payment_type;
            $orderId = $notification->order_id;
            $fraud = $notification->fraud_status;

            // Find Order
            $order = Order::where('order_number', $orderId)->firstOrFail();

            if ($transaction == 'capture') {
                if ($type == 'credit_card') {
                    if ($fraud == 'challenge') {
                        $order->payment_status = 'challenge';
                    } else {
                        $order->payment_status = 'paid';
                        $order->paid_at = now();
                    }
                }
            } else if ($transaction == 'settlement') {
                $order->payment_status = 'paid';
                $order->paid_at = now();
            } else if ($transaction == 'pending') {
                $order->payment_status = 'pending';
            } else if ($transaction == 'deny') {
                $order->payment_status = 'failed';
            } else if ($transaction == 'expire') {
                $order->payment_status = 'expired';
            } else if ($transaction == 'cancel') {
                $order->payment_status = 'cancelled';
            }

            $order->payment_method = $type;
            $order->save();

            Log::info("Midtrans Webhook: Order $orderId updated to $transaction");

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Midtrans Webhook Error: ' . $e->getMessage());
            return response()->json(['success' => false], 500);
        }
    }
}

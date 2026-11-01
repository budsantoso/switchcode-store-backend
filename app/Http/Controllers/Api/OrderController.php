<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Create Checkout Order & Midtrans Snap Token
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:100',
            'customer_email' => 'required|email|max:100',
            'customer_phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.topping_ids' => 'nullable|array',
            'items.*.topping_ids.*' => 'integer',
        ]);

        return DB::transaction(function () use ($validated) {
            $orderNumber = 'ORD-' . date('YmdHis') . '-' . strtoupper(Str::random(4));
            $totalAmount = 0;
            $itemsData = [];

            foreach ($validated['items'] as $itemInput) {
                $product = Product::with('modules')->findOrFail($itemInput['product_id']);
                $qty = (int) ($itemInput['quantity'] ?? 1);
                
                // Kalkulasi harga termasuk topping
                $itemUnitPrice = (float) $product->base_price;
                $toppingNotes = [];

                if (!empty($itemInput['topping_ids']) && is_array($itemInput['topping_ids'])) {
                    $toppings = $product->modules->whereIn('id', $itemInput['topping_ids']);
                    foreach ($toppings as $top) {
                        $itemUnitPrice += (float) $top->price;
                        $toppingNotes[] = $top->name . ' (+Rp ' . number_format($top->price, 0, ',', '.') . ')';
                    }
                }

                $subtotal = $itemUnitPrice * $qty;
                $totalAmount += $subtotal;

                $prodName = $product->name;
                if (!empty($toppingNotes)) {
                    $prodName .= ' [Topping: ' . implode(', ', $toppingNotes) . ']';
                }

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $prodName,
                    'price' => $itemUnitPrice,
                    'quantity' => $qty,
                    'subtotal' => $subtotal,
                ];
            }

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_gateway' => 'midtrans',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($itemsData as $it) {
                $order->items()->create($it);
            }

            // Midtrans Snap API Request (jika server key diset)
            $serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY'));
            $isProd = config('services.midtrans.is_production', env('MIDTRANS_IS_PRODUCTION', false));
            $snapUrl = $isProd 
                ? 'https://app.midtrans.com/snap/v1/transactions' 
                : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

            $snapToken = null;
            $paymentUrl = null;

            if ($serverKey) {
                try {
                    $snapPayload = [
                        'transaction_details' => [
                            'order_id' => $order->order_number,
                            'gross_amount' => (int) $totalAmount,
                        ],
                        'customer_details' => [
                            'first_name' => $order->customer_name,
                            'email' => $order->customer_email,
                            'phone' => $order->customer_phone,
                        ],
                    ];

                    $res = Http::withBasicAuth($serverKey, '')
                        ->post($snapUrl, $snapPayload);

                    if ($res->successful()) {
                        $json = $res->json();
                        $snapToken = $json['token'] ?? null;
                        $paymentUrl = $json['redirect_url'] ?? null;
                        
                        $order->update([
                            'snap_token' => $snapToken,
                            'payment_url' => $paymentUrl,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Fallback to manual flow
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat.',
                'data' => [
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'snap_token' => $snapToken,
                    'payment_url' => $paymentUrl,
                ]
            ], 201);
        });
    }

    /**
     * Check Order Status
     */
    public function show($orderNumber)
    {
        $order = Order::with('items')->where('order_number', $orderNumber)->firstOrFail();
        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Admin/Owner: List Orders for Tracking
     */
    public function adminIndex(Request $request)
    {
        $query = Order::with('items')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $s = $request->string('search');
            $query->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%")
                  ->orWhere('customer_email', 'like', "%{$s}%");
            });
        }

        $orders = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Admin/Owner: Update Order Status
     */
    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,processing,completed,cancelled',
        ]);

        $order = Order::findOrFail($id);
        $order->status = $validated['status'];
        if ($validated['status'] === 'paid' && !$order->paid_at) {
            $order->paid_at = now();
        }
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Status pesanan berhasil diperbarui',
            'data' => $order
        ]);
    }

    /**
     * Webhook Handler Midtrans (Hardened with SHA512 Signature & Idempotency Lock)
     */
    public function midtransWebhook(Request $request)
    {
        $notif = $request->all();
        $orderId = $notif['order_id'] ?? null;
        $statusCode = $notif['status_code'] ?? null;
        $grossAmount = $notif['gross_amount'] ?? null;
        $signatureKey = $notif['signature_key'] ?? null;
        $transactionStatus = $notif['transaction_status'] ?? null;
        $fraudStatus = $notif['fraud_status'] ?? null;

        if (!$orderId) {
            return response()->json(['message' => 'Invalid order id'], 400);
        }

        // 1. Signature Verification
        $serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY'));
        if ($serverKey && $signatureKey) {
            $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
            if (!hash_equals($expectedSignature, $signatureKey)) {
                return response()->json(['message' => 'Invalid signature key'], 403);
            }
        }

        return DB::transaction(function () use ($orderId, $transactionStatus, $fraudStatus) {
            $order = Order::where('order_number', $orderId)->lockForUpdate()->first();
            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Idempotency: Jika status sudah final 'paid', abaikan webhook duplikat
            if ($order->status === 'paid' && in_array($transactionStatus, ['settlement', 'capture'])) {
                return response()->json(['success' => true, 'message' => 'Already settled']);
            }

            if ($transactionStatus == 'capture') {
                if ($fraudStatus == 'challenge') {
                    $order->status = 'challenge';
                } else if ($fraudStatus == 'accept') {
                    $order->status = 'paid';
                    $order->paid_at = now();
                }
            } else if ($transactionStatus == 'settlement') {
                $order->status = 'paid';
                $order->paid_at = now();
            } else if (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
                $order->status = 'cancelled';
            } else if ($transactionStatus == 'pending') {
                $order->status = 'pending';
            }

            $order->save();

            return response()->json(['success' => true]);
        });
    }
}

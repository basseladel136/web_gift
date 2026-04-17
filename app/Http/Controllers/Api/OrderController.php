<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * List the authenticated user's orders.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items.product')
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    /**
     * Place a new order from the user's cart.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $cartItems = CartItem::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty.',
            ], 422);
        }

        // Validate stock for all items before placing
        foreach ($cartItems as $item) {
            if (! $item->product->is_active) {
                return response()->json([
                    'message' => "Product \"{$item->product->name}\" is no longer available.",
                ], 422);
            }

            if ($item->quantity > $item->product->stock) {
                return response()->json([
                    'message' => "Insufficient stock for \"{$item->product->name}\". Only {$item->product->stock} left.",
                ], 422);
            }
        }

        // Calculate subtotal
        $subtotal = $cartItems->sum(
            fn($item) => $item->product->price * $item->quantity
        );

        // Apply coupon if provided
        $discount = 0;
        $couponId = null;

        if ($request->filled('coupon_code')) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();

            if (! $coupon || ! $coupon->isValid($subtotal)) {
                return response()->json([
                    'message' => 'Coupon is invalid or expired.',
                ], 422);
            }

            $discount = $coupon->calculateDiscount($subtotal);
            $couponId = $coupon->id;
        }

        $total = max(0, $subtotal - $discount);

        // Wrap everything in a transaction
        $order = DB::transaction(function () use (
            $request,
            $cartItems,
            $subtotal,
            $discount,
            $total,
            $couponId
        ) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'coupon_id' => $couponId,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'gift_message' => $request->gift_message,
                'payment_status' => 'unpaid',
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product->id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->product->price,
                ]);

                // Deduct stock
                $item->product->decrement('stock', $item->quantity);
            }

            // Clear the cart
            CartItem::where('user_id', $request->user()->id)->delete();
            // Increment coupon usage
            if ($couponId) {
                Coupon::where('id', $couponId)->increment('used_count');
            }

            return $order;
        });

        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => $order->load('items.product'),
        ], 201);
    }

    /**
     * Show a single order (only owner can view).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->with('items.product')
            ->findOrFail($id);

        return response()->json([
            'order' => $order,
        ]);
    }

    /**
     * Update order status (admin only).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Order status updated.',
            'order' => $order,
        ]);
    }
}

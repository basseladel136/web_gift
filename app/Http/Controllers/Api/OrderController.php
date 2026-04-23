<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * @group Orders
 *
 * APIs for placing and managing orders.
 * All endpoints require authentication.
 *
 * @authenticated
 */
class OrderController extends Controller
{
    /**
     * List my orders
     *
     * Returns all orders for the authenticated user.
     *
     * @response 200 {
     *   "orders": [
     *     {
     *       "id": 1,
     *       "status": "pending",
     *       "subtotal": "1000.00",
     *       "discount": "100.00",
     *       "total": "900.00",
     *       "gift_message": "Happy Birthday!",
     *       "payment_status": "unpaid",
     *       "created_at": "2026-04-22T10:00:00.000000Z",
     *       "items": []
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items.product')
            ->latest()
            ->get();

        return ApiResponse::success('OK', ['orders' => $orders]);
    }

    /**
     * Place an order
     *
     * Places a new order from the authenticated user's cart.
     * Cart is cleared automatically after the order is placed.
     * Stock is deducted for each item.
     *
     * @bodyParam gift_message string optional A personal gift message. Example: Happy Birthday!
     * @bodyParam coupon_code string optional A valid coupon code. Example: SAVE10
     *
     * @response 201 {
     *   "message": "Order placed successfully.",
     *   "order": {
     *     "id": 1,
     *     "status": "pending",
     *     "subtotal": "1000.00",
     *     "discount": "100.00",
     *     "total": "900.00",
     *     "gift_message": "Happy Birthday!",
     *     "payment_status": "unpaid",
     *     "items": []
     *   }
     * }
     * @response 422 { "message": "Your cart is empty." }
     * @response 422 { "message": "Coupon is invalid or expired." }
     * @response 422 { "message": "Insufficient stock for \"Rolex\". Only 2 left." }
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $cartItems = CartItem::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return ApiResponse::error(__('messages.cart_empty'), 422);
        }

        foreach ($cartItems as $item) {
            if (! $item->product->is_active) {
                return ApiResponse::error(
                    __('messages.product_unavailable', ['name' => $item->product->name]),
                    422
                );
            }

            if ($item->quantity > $item->product->stock) {
                return ApiResponse::error(
                    __('messages.insufficient_stock', [
                        'name'  => $item->product->name,
                        'stock' => $item->product->stock,
                    ]),
                    422
                );
            }
        }

        $subtotal = $cartItems->sum(
            fn($item) => $item->product->price * $item->quantity
        );

        $discount = 0;
        $couponId = null;

        if ($request->filled('coupon_code')) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();

            if (! $coupon || ! $coupon->isValid($subtotal)) {
                return ApiResponse::error(__('messages.coupon_invalid'), 422);
            }

            $discount = $coupon->calculateDiscount($subtotal);
            $couponId = $coupon->id;
        }

        $total = max(0, $subtotal - $discount);

        $order = DB::transaction(function () use (
            $request,
            $cartItems,
            $subtotal,
            $discount,
            $total,
            $couponId
        ) {
            $order = Order::create([
                'user_id'        => $request->user()->id,
                'coupon_id'      => $couponId,
                'status'         => 'pending',
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'total'          => $total,
                'gift_message'   => $request->gift_message,
                'payment_status' => 'unpaid',
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item->product->id,
                    'quantity'   => $item->quantity,
                    'unit_price' => $item->product->price,
                ]);

                $item->product->decrement('stock', $item->quantity);
            }

            CartItem::where('user_id', $request->user()->id)->delete();

            if ($couponId) {
                Coupon::where('id', $couponId)->increment('used_count');
            }

            return $order;
        });

        Mail::to($request->user()->email)
            ->send(new OrderPlacedMail($order->load('items.product', 'user')));

        return ApiResponse::success(
            __('messages.order_placed'),
            ['order' => $order->load('items.product')],
            201
        );
    }

    /**
     * Get single order
     *
     * Returns details of a specific order belonging to the authenticated user.
     *
     * @urlParam id integer required Order ID. Example: 1
     *
     * @response 200 {
     *   "order": {
     *     "id": 1,
     *     "status": "pending",
     *     "total": "900.00",
     *     "items": []
     *   }
     * }
     * @response 404 { "message": "No query results for model [Order] 1" }
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->with('items.product')
            ->findOrFail($id);

        return ApiResponse::success('OK', ['order' => $order]);
    }

    /**
     * Update order status (Admin)
     *
     * Updates the status of any order. Sends email notification to the customer.
     *
     * @urlParam id integer required Order ID. Example: 1
     *
     * @bodyParam status string required New order status. Example: confirmed
     * Allowed values: pending, confirmed, processing, shipped, delivered, cancelled
     *
     * @response 200 {
     *   "message": "Order status updated.",
     *   "order": {
     *     "id": 1,
     *     "status": "confirmed"
     *   }
     * }
     * @response 404 { "message": "No query results for model [Order] 1" }
     * @response 422 { "message": "The status field is required." }
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        Mail::to($order->user->email)
            ->send(new OrderStatusUpdatedMail($order->load('user')));

        return ApiResponse::success(
            __('messages.order_status_updated'),
            ['order' => $order]
        );
    }
}
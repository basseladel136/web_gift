<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\ApplyCouponRequest;
use App\Http\Requests\Coupon\StoreCouponRequest;
use App\Http\Requests\Coupon\UpdateCouponRequest;
use App\Models\CartItem;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Coupons
 *
 * APIs for managing and applying discount coupons.
 */
class CouponController extends Controller
{
    /**
     * List all coupons (Admin)
     *
     * @authenticated
     *
     * @response 200 {
     *   "coupons": [
     *     {
     *       "id": 1,
     *       "code": "SAVE10",
     *       "type": "percent",
     *       "value": "10.00",
     *       "min_order_amount": "100.00",
     *       "max_uses": 50,
     *       "used_count": 5,
     *       "is_active": true,
     *       "expires_at": "2026-12-31T00:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $coupons = Coupon::latest()->get();

        return response()->json([
            'coupons' => $coupons,
        ]);
    }

    /**
     * Create a coupon (Admin)
     *
     * @authenticated
     *
     * @bodyParam code string required Unique coupon code. Example: SAVE10
     * @bodyParam type string required Discount type: percent or fixed. Example: percent
     * @bodyParam value number required Discount value. Example: 10
     * @bodyParam min_order_amount number optional Minimum order amount required. Example: 100
     * @bodyParam max_uses integer optional Maximum number of uses. Example: 50
     * @bodyParam is_active boolean optional Whether coupon is active. Example: true
     * @bodyParam expires_at date optional Expiry date. Example: 2026-12-31
     *
     * @response 201 {
     *   "message": "Coupon created successfully.",
     *   "coupon": {
     *     "id": 1,
     *     "code": "SAVE10",
     *     "type": "percent",
     *     "value": "10.00"
     *   }
     * }
     * @response 422 { "message": "The code has already been taken." }
     */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->validated());

        return response()->json([
            'message' => __('messages.coupon_created'),
            'coupon'  => $coupon,
        ], 201);
    }

    /**
     * Update a coupon (Admin)
     *
     * @authenticated
     *
     * @urlParam id integer required Coupon ID. Example: 1
     * @bodyParam code string optional Coupon code. Example: SAVE20
     * @bodyParam type string optional Discount type: percent or fixed. Example: fixed
     * @bodyParam value number optional Discount value. Example: 50
     * @bodyParam min_order_amount number optional Minimum order amount. Example: 200
     * @bodyParam max_uses integer optional Maximum uses. Example: 100
     * @bodyParam is_active boolean optional Whether coupon is active. Example: false
     * @bodyParam expires_at date optional Expiry date. Example: 2027-01-01
     *
     * @response 200 {
     *   "message": "Coupon updated successfully.",
     *   "coupon": {}
     * }
     * @response 404 { "message": "No query results for model [Coupon] 1" }
     */
    public function update(UpdateCouponRequest $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update($request->validated());

        return response()->json([
            'message' => __('messages.coupon_updated'),
            'coupon'  => $coupon,
        ]);
    }

    /**
     * Delete a coupon (Admin)
     *
     * @authenticated
     *
     * @urlParam id integer required Coupon ID. Example: 1
     *
     * @response 200 { "message": "Coupon deleted successfully." }
     * @response 404 { "message": "No query results for model [Coupon] 1" }
     */
    public function destroy(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'message' => __('messages.coupon_deleted'),
        ]);
    }

    /**
     * Apply a coupon
     *
     * Preview the discount a coupon gives against the current cart.
     * Does not place an order — use this before checkout to show the user the discount.
     *
     * @authenticated
     *
     * @bodyParam code string required Coupon code to apply. Example: SAVE10
     *
     * @response 200 {
     *   "message": "Coupon applied successfully.",
     *   "coupon": "SAVE10",
     *   "subtotal": 1000.00,
     *   "discount": 100.00,
     *   "total": 900.00
     * }
     * @response 422 { "message": "Your cart is empty." }
     * @response 422 { "message": "Coupon is invalid or expired." }
     * @response 422 { "message": "This coupon has expired." }
     * @response 422 { "message": "This coupon has reached its usage limit." }
     * @response 422 { "message": "A minimum order of 100 is required to use this coupon." }
     */
    public function apply(ApplyCouponRequest $request): JsonResponse
    {
        $cartItems = CartItem::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => __('messages.cart_empty'),
            ], 422);
        }

        $subtotal = $cartItems->sum(
            fn($item) => $item->product->price * $item->quantity
        );

        $coupon = Coupon::where('code', $request->code)->first();

        if (! $coupon) {
            return response()->json([
                'message' => __('messages.coupon_invalid'),
            ], 422);
        }

        if (! $coupon->isValid($subtotal)) {
            return response()->json([
                'message' => $this->invalidReason($coupon, $subtotal),
            ], 422);
        }

        $discount = $coupon->calculateDiscount($subtotal);
        $total    = max(0, $subtotal - $discount);

        return response()->json([
            'message'  => __('messages.coupon_applied'),
            'coupon'   => $coupon->code,
            'subtotal' => round($subtotal, 2),
            'discount' => $discount,
            'total'    => round($total, 2),
        ]);
    }

    /**
     * Return a human-readable reason why the coupon is invalid.
     */
    private function invalidReason(Coupon $coupon, float $subtotal): string
    {
        if (! $coupon->is_active) {
            return __('messages.coupon_inactive');
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return __('messages.coupon_expired');
        }

        if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
            return __('messages.coupon_limit_reached');
        }

        if ($subtotal < $coupon->min_order_amount) {
            return __('messages.coupon_min_order', [
                'amount' => $coupon->min_order_amount,
            ]);
        }

        return __('messages.coupon_invalid');
    }
}
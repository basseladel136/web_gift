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

class CouponController extends Controller
{
    /**
     * List all coupons (admin only).
     */
    public function index(): JsonResponse
    {
        $coupons = Coupon::latest()->get();

        return response()->json([
            'coupons' => $coupons,
        ]);
    }

    /**
     * Create a new coupon (admin only).
     */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->validated());

        return response()->json([
            'message' => 'Coupon created successfully.',
            'coupon'  => $coupon,
        ], 201);
    }

    /**
     * Update a coupon (admin only).
     */
    public function update(UpdateCouponRequest $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update($request->validated());

        return response()->json([
            'message' => 'Coupon updated successfully.',
            'coupon'  => $coupon,
        ]);
    }

    /**
     * Delete a coupon (admin only).
     */
    public function destroy(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully.',
        ]);
    }

    /**
     * Apply a coupon — preview the discount against the user's cart.
     */
    public function apply(ApplyCouponRequest $request): JsonResponse
    {
        $cartItems = CartItem::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty.',
            ], 422);
        }

        $subtotal = $cartItems->sum(
            fn($item) => $item->product->price * $item->quantity
        );

        $coupon = Coupon::where('code', $request->code)->first();

        if (! $coupon) {
            return response()->json([
                'message' => 'Invalid coupon code'
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
            'message'   => 'Coupon applied successfully.',
            'coupon'    => $coupon->code,
            'subtotal'  => round($subtotal, 2),
            'discount'  => $discount,
            'total'     => round($total, 2),
        ]);
    }

    /**
     * Return a human-readable reason why the coupon is invalid.
     */
    private function invalidReason(Coupon $coupon, float $subtotal): string
    {
        if (! $coupon->is_active) {
            return 'This coupon is no longer active.';
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return 'This coupon has expired.';
        }

        if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
            return 'This coupon has reached its usage limit.';
        }

        if ($subtotal < $coupon->min_order_amount) {
            return "A minimum order of {$coupon->min_order_amount} is required to use this coupon.";
        }

        return 'This coupon is invalid.';
    }
}

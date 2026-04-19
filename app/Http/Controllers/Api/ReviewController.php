<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * List all reviews for a product with average rating.
     */
    public function index(int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $reviews = Review::with('user:id,name')
            ->where('product_id', $productId)
            ->latest()
            ->get();

        return response()->json([
            'product_id'     => $productId,
            'average_rating' => $product->average_rating,
            'total_reviews'  => $reviews->count(),
            'reviews'        => $reviews,
        ]);
    }

    /**
     * Submit a review — user must have a delivered order containing this product.
     */
    public function store(StoreReviewRequest $request, int $productId): JsonResponse
    {
        $product = Product::where('is_active', true)->findOrFail($productId);

        // Check user has purchased and received this product
        $hasPurchased = Order::where('user_id', $request->user()->id)
            ->where('status', 'delivered')
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->exists();

        if (! $hasPurchased) {
            return response()->json([
                'message' => 'You can only review products you have purchased and received.',
            ], 403);
        }

        // Check if already reviewed
        $existing = Review::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You have already reviewed this product.',
            ], 422);
        }

        $review = Review::create([
            'user_id'    => $request->user()->id,
            'product_id' => $productId,
            'rating'     => $request->rating,
            'comment'    => $request->comment,
        ]);

        return response()->json([
            'message' => 'Review submitted successfully.',
            'review'  => $review->load('user:id,name'),
        ], 201);
    }

    /**
     * Update own review.
     */
    public function update(StoreReviewRequest $request, int $productId): JsonResponse
    {
        $review = Review::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->firstOrFail();

        $review->update([
            'rating'  => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Review updated successfully.',
            'review'  => $review->load('user:id,name'),
        ]);
    }

    /**
     * Delete own review.
     */
    public function destroy(Request $request, int $productId): JsonResponse
    {
        $review = Review::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->firstOrFail();

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully.',
        ]);
    }
}
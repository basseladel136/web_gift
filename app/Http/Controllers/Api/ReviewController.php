<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Reviews
 *
 * APIs for submitting and managing product reviews.
 */
class ReviewController extends Controller
{
    /**
     * List reviews for a product
     *
     * Returns all reviews for a product with average rating.
     * Available to everyone — no authentication required.
     *
     * @urlParam productId integer required Product ID. Example: 1
     *
     * @response 200 {
     *   "product_id": 1,
     *   "average_rating": 4.5,
     *   "total_reviews": 10,
     *   "reviews": [
     *     {
     *       "id": 1,
     *       "rating": 5,
     *       "comment": "Amazing watch!",
     *       "created_at": "2026-04-22T10:00:00.000000Z",
     *       "user": { "id": 1, "name": "Bassel" }
     *     }
     *   ]
     * }
     * @response 404 { "message": "No query results for model [Product] 1" }
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
     * Submit a review
     *
     * Submit a review for a product.
     * User must have a delivered order containing this product.
     * Only one review per product per user is allowed.
     *
     * @authenticated
     *
     * @urlParam productId integer required Product ID. Example: 1
     *
     * @bodyParam rating integer required Rating from 1 to 5. Example: 5
     * @bodyParam comment string optional Review comment. Example: Amazing watch, exactly as described!
     *
     * @response 201 {
     *   "message": "Review submitted successfully.",
     *   "review": {
     *     "id": 1,
     *     "rating": 5,
     *     "comment": "Amazing watch!",
     *     "user": { "id": 1, "name": "Bassel" }
     *   }
     * }
     * @response 403 { "message": "You can only review products you have purchased and received." }
     * @response 422 { "message": "You have already reviewed this product." }
     * @response 404 { "message": "No query results for model [Product] 1" }
     */
    public function store(StoreReviewRequest $request, int $productId): JsonResponse
    {
        $product = Product::where('is_active', true)->findOrFail($productId);

        $hasPurchased = Order::where('user_id', $request->user()->id)
            ->where('status', 'delivered')
            ->whereHas('items', fn($q) => $q->where('product_id', $productId))
            ->exists();

        if (! $hasPurchased) {
            return response()->json([
                'message' => __('messages.review_not_purchased'),
            ], 403);
        }

        $existing = Review::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => __('messages.review_exists'),
            ], 422);
        }

        $review = Review::create([
            'user_id'    => $request->user()->id,
            'product_id' => $productId,
            'rating'     => $request->rating,
            'comment'    => $request->comment,
        ]);

        return response()->json([
            'message' => __('messages.review_submitted'),
            'review'  => $review->load('user:id,name'),
        ], 201);
    }

    /**
     * Update own review
     *
     * Update a previously submitted review for a product.
     *
     * @authenticated
     *
     * @urlParam productId integer required Product ID. Example: 1
     *
     * @bodyParam rating integer required New rating from 1 to 5. Example: 4
     * @bodyParam comment string optional Updated comment. Example: Great product, updated review.
     *
     * @response 200 {
     *   "message": "Review updated successfully.",
     *   "review": {
     *     "id": 1,
     *     "rating": 4,
     *     "comment": "Great product, updated review.",
     *     "user": { "id": 1, "name": "Bassel" }
     *   }
     * }
     * @response 404 { "message": "No query results for model [Review]" }
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
            'message' => __('messages.review_updated'),
            'review'  => $review->load('user:id,name'),
        ]);
    }

    /**
     * Delete own review
     *
     * Delete a previously submitted review for a product.
     *
     * @authenticated
     *
     * @urlParam productId integer required Product ID. Example: 1
     *
     * @response 200 { "message": "Review deleted successfully." }
     * @response 404 { "message": "No query results for model [Review]" }
     */
    public function destroy(Request $request, int $productId): JsonResponse
    {
        $review = Review::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->firstOrFail();

        $review->delete();

        return response()->json([
            'message' => __('messages.review_deleted'),
        ]);
    }
}
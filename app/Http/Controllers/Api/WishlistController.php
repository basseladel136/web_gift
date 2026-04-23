<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Wishlist
 *
 * APIs for managing the wishlist.
 * All endpoints require authentication.
 *
 * @authenticated
 */
class WishlistController extends Controller
{
    /**
     * View wishlist
     *
     * Returns all products in the authenticated user's wishlist.
     *
     * @response 200 {
     *   "wishlist": [
     *     {
     *       "id": 1,
     *       "product": {
     *         "id": 1,
     *         "name": "Rolex Submariner",
     *         "price": "5000.00",
     *         "image": "rolex.jpg",
     *         "brand": "Rolex",
     *         "stock": 10,
     *         "category": "Watches"
     *       }
     *     }
     *   ],
     *   "total": 1
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::with('product.category')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'wishlist' => $items->map(fn($item) => [
                'id'      => $item->id,
                'product' => [
                    'id'       => $item->product->id,
                    'name'     => $item->product->name,
                    'price'    => $item->product->price,
                    'image'    => $item->product->image,
                    'brand'    => $item->product->brand,
                    'stock'    => $item->product->stock,
                    'category' => $item->product->category->name,
                ],
            ]),
            'total' => $items->count(),
        ]);
    }

    /**
     * Add to wishlist
     *
     * Adds a product to the authenticated user's wishlist.
     *
     * @bodyParam product_id integer required Product ID. Example: 1
     *
     * @response 201 {
     *   "message": "Product added to wishlist.",
     *   "item": { "id": 1, "user_id": 1, "product_id": 1 }
     * }
     * @response 422 { "message": "Product is already in your wishlist." }
     * @response 404 { "message": "No query results for model [Product] 1" }
     */
    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::where('is_active', true)
            ->findOrFail($request->product_id);

        $already = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($already) {
            return response()->json([
                'message' => __('messages.wishlist_exists'),
            ], 422);
        }

        $item = Wishlist::create([
            'user_id'    => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'message' => __('messages.wishlist_added'),
            'item'    => $item,
        ], 201);
    }

    /**
     * Remove from wishlist
     *
     * Removes a product from the authenticated user's wishlist.
     *
     * @urlParam id integer required Wishlist item ID. Example: 1
     *
     * @response 200 { "message": "Product removed from wishlist." }
     * @response 404 { "message": "No query results for model [Wishlist]" }
     */
    public function remove(Request $request, int $id): JsonResponse
    {
        $item = Wishlist::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $item->delete();

        return response()->json([
            'message' => __('messages.wishlist_removed'),
        ]);
    }

    /**
     * Move to cart
     *
     * Moves a wishlist item directly to the cart and removes it from the wishlist.
     *
     * @urlParam id integer required Wishlist item ID. Example: 1
     *
     * @response 200 { "message": "Product moved to cart." }
     * @response 422 { "message": "This product is no longer available." }
     * @response 422 { "message": "This product is out of stock." }
     * @response 422 { "message": "Only 5 items available in stock." }
     * @response 404 { "message": "No query results for model [Wishlist]" }
     */
    public function moveToCart(Request $request, int $id): JsonResponse
    {
        $item = Wishlist::with('product')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $product = $item->product;

        if (! $product->is_active) {
            return response()->json([
                'message' => __('messages.product_unavailable'),
            ], 422);
        }

        if ($product->stock < 1) {
            return response()->json([
                'message' => __('messages.product_out_of_stock'),
            ], 422);
        }

        $cartItem = CartItem::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + 1;

            if ($newQuantity > $product->stock) {
                return response()->json([
                    'message' => __('messages.stock_exceeded', ['stock' => $product->stock]),
                ], 422);
            }

            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            CartItem::create([
                'user_id'    => $request->user()->id,
                'product_id' => $product->id,
                'quantity'   => 1,
            ]);
        }

        $item->delete();

        return response()->json([
            'message' => __('messages.wishlist_moved'),
        ]);
    }
}
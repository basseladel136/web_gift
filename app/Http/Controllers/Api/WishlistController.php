<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * List all wishlist items for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::with('product.category')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'wishlist' => $items->map(fn ($item) => [
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
     * Add a product to the wishlist.
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
                'message' => 'Product is already in your wishlist.',
            ], 422);
        }

        $item = Wishlist::create([
            'user_id'    => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'message' => 'Product added to wishlist.',
            'item'    => $item,
        ], 201);
    }

    /**
     * Remove a product from the wishlist.
     */
    public function remove(Request $request, int $id): JsonResponse
    {
        $item = Wishlist::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $item->delete();

        return response()->json([
            'message' => 'Product removed from wishlist.',
        ]);
    }

    /**
     * Move a wishlist item directly to the cart.
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
                'message' => 'This product is no longer available.',
            ], 422);
        }

        if ($product->stock < 1) {
            return response()->json([
                'message' => 'This product is out of stock.',
            ], 422);
        }

        // Add to cart or increment if already there
        $cartItem = \App\Models\CartItem::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + 1;

            if ($newQuantity > $product->stock) {
                return response()->json([
                    'message' => "Only {$product->stock} items available in stock.",
                ], 422);
            }

            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            \App\Models\CartItem::create([
                'user_id'    => $request->user()->id,
                'product_id' => $product->id,
                'quantity'   => 1,
            ]);
        }

        // Remove from wishlist
        $item->delete();

        return response()->json([
            'message' => 'Product moved to cart.',
        ]);
    }
}
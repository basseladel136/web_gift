<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * View the authenticated user's cart with total price.
     */
    public function index(Request $request): JsonResponse
    {
        $items = CartItem::with('product.category')
            ->where('user_id', $request->user()->id)
            ->get();

        $total = $items->sum(fn ($item) => $item->product->price * $item->quantity);

        return response()->json([
            'items' => $items->map(fn ($item) => [
                'id'       => $item->id,
                'quantity' => $item->quantity,
                'subtotal' => round($item->product->price * $item->quantity, 2),
                'product'  => [
                    'id'       => $item->product->id,
                    'name'     => $item->product->name,
                    'price'    => $item->product->price,
                    'image'    => $item->product->image,
                    'brand'    => $item->product->brand,
                    'stock'    => $item->product->stock,
                    'category' => $item->product->category->name,
                ],
            ]),
            'total'      => round($total, 2),
            'item_count' => $items->sum('quantity'),
        ]);
    }

    /**
     * Add a product to the cart.
     * If it already exists, increment the quantity.
     */
    public function add(AddToCartRequest $request): JsonResponse
    {
        $product = Product::where('is_active', true)
            ->findOrFail($request->product_id);

        $cartItem = CartItem::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $request->quantity;

            if ($newQuantity > $product->stock) {
                return response()->json([
                    'message' => "Only {$product->stock} items available in stock.",
                ], 422);
            }

            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            if ($request->quantity > $product->stock) {
                return response()->json([
                    'message' => "Only {$product->stock} items available in stock.",
                ], 422);
            }

            $cartItem = CartItem::create([
                'user_id'    => $request->user()->id,
                'product_id' => $product->id,
                'quantity'   => $request->quantity,
            ]);
        }

        return response()->json([
            'message'  => 'Item added to cart.',
            'cart_item' => [
                'id'         => $cartItem->id,
                'product_id' => $cartItem->product_id,
                'quantity'   => $cartItem->quantity,
            ],
        ], 201);
    }

    /**
     * Update the quantity of a cart item.
     */
    public function update(UpdateCartRequest $request): JsonResponse
    {
        $cartItem = CartItem::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->firstOrFail();

        $product = Product::findOrFail($request->product_id);

        if ($request->quantity > $product->stock) {
            return response()->json([
                'message' => "Only {$product->stock} items available in stock.",
            ], 422);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json([
            'message'  => 'Cart updated.',
            'cart_item' => [
                'id'         => $cartItem->id,
                'product_id' => $cartItem->product_id,
                'quantity'   => $cartItem->quantity,
            ],
        ]);
    }

    /**
     * Remove an item from the cart.
     */
    public function remove(Request $request, int $id): JsonResponse
    {
        $cartItem = CartItem::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->delete();

        return response()->json([
            'message' => 'Item removed from cart.',
        ]);
    }
}
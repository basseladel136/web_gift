<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Cart
 *
 * APIs for managing the shopping cart.
 * All endpoints require authentication.
 *
 * @authenticated
 */
class CartController extends Controller
{
    /**
     * View cart
     *
     * Returns all cart items with subtotals and total price.
     *
     * @response 200 {
     *   "items": [
     *     {
     *       "id": 1,
     *       "quantity": 2,
     *       "subtotal": 10000.00,
     *       "product": {
     *         "id": 1,
     *         "name": "Rolex Submariner",
     *         "price": "5000.00",
     *         "image": "rolex.jpg",
     *         "brand": "Rolex",
     *         "stock": 8,
     *         "category": "Watches"
     *       }
     *     }
     *   ],
     *   "total": 10000.00,
     *   "item_count": 2
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $items = CartItem::with('product.category')
            ->where('user_id', $request->user()->id)
            ->get();

        $total = $items->sum(fn($item) => $item->product->price * $item->quantity);

        return response()->json([
            'items' => $items->map(fn($item) => [
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
     * Add item to cart
     *
     * Adds a product to the cart. If the product already exists,
     * the quantity will be incremented.
     *
     * @bodyParam product_id integer required Product ID. Example: 1
     * @bodyParam quantity integer required Quantity to add. Example: 2
     *
     * @response 201 {
     *   "message": "Item added to cart.",
     *   "cart_item": {
     *     "id": 1,
     *     "product_id": 1,
     *     "quantity": 2
     *   }
     * }
     * @response 422 { "message": "Only 5 items available in stock." }
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
                    'message' => __('messages.stock_exceeded', ['stock' => $product->stock]),
                ], 422);
            }

            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            if ($request->quantity > $product->stock) {
                return response()->json([
                    'message' => __('messages.stock_exceeded', ['stock' => $product->stock]),
                ], 422);
            }

            $cartItem = CartItem::create([
                'user_id'    => $request->user()->id,
                'product_id' => $product->id,
                'quantity'   => $request->quantity,
            ]);
        }

        return response()->json([
            'message'   => __('messages.cart_item_added'),
            'cart_item' => [
                'id'         => $cartItem->id,
                'product_id' => $cartItem->product_id,
                'quantity'   => $cartItem->quantity,
            ],
        ], 201);
    }

    /**
     * Update cart item quantity
     *
     * @bodyParam product_id integer required Product ID. Example: 1
     * @bodyParam quantity integer required New quantity. Example: 3
     *
     * @response 200 {
     *   "message": "Cart updated.",
     *   "cart_item": {
     *     "id": 1,
     *     "product_id": 1,
     *     "quantity": 3
     *   }
     * }
     * @response 422 { "message": "Only 5 items available in stock." }
     * @response 404 { "message": "No query results for model [CartItem]" }
     */
    public function update(UpdateCartRequest $request): JsonResponse
    {
        $cartItem = CartItem::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->firstOrFail();

        $product = Product::findOrFail($request->product_id);

        if ($request->quantity > $product->stock) {
            return response()->json([
                'message' => __('messages.stock_exceeded', ['stock' => $product->stock]),
            ], 422);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json([
            'message'   => __('messages.cart_item_updated'),
            'cart_item' => [
                'id'         => $cartItem->id,
                'product_id' => $cartItem->product_id,
                'quantity'   => $cartItem->quantity,
            ],
        ]);
    }

    /**
     * Remove item from cart
     *
     * @urlParam id integer required Cart item ID. Example: 1
     *
     * @response 200 { "message": "Item removed from cart." }
     * @response 404 { "message": "Cart item not found." }
     */
    public function remove(Request $request, int $id): JsonResponse
    {
        $cartItem = CartItem::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $cartItem) {
            return response()->json([
                'message' => 'Cart item not found.',
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'message' => __('messages.cart_item_removed'),
        ]);
    }
}
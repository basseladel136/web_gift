<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Products
 *
 * APIs for browsing and managing products.
 */
class ProductController extends Controller
{
    /**
     * List products
     *
     * Returns paginated list of active products with optional filters.
     *
     * @queryParam category_id integer Filter by category ID. Example: 1
     * @queryParam brand string Filter by brand name. Example: Rolex
     * @queryParam min_price number Filter by minimum price. Example: 100
     * @queryParam max_price number Filter by maximum price. Example: 5000
     * @queryParam search string Search by name or description. Example: rolex
     *
     * @response 200 {
     *   "current_page": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Rolex Submariner",
     *       "price": "5000.00",
     *       "stock": 10,
     *       "brand": "Rolex",
     *       "image": "rolex.jpg",
     *       "is_active": true,
     *       "category": { "id": 1, "name": "Watches" }
     *     }
     *   ],
     *   "per_page": 12,
     *   "total": 50
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category')->where('is_active', true);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->string('brand'));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->latest()->paginate(12);

        return response()->json($products);
    }

    /**
     * Get single product
     *
     * @urlParam id integer required Product ID. Example: 1
     *
     * @response 200 {
     *   "product": {
     *     "id": 1,
     *     "name": "Rolex Submariner",
     *     "price": "5000.00",
     *     "stock": 10,
     *     "brand": "Rolex",
     *     "description": "Luxury dive watch",
     *     "image": "rolex.jpg",
     *     "is_active": true,
     *     "category": { "id": 1, "name": "Watches" }
     *   }
     * }
     * @response 404 { "message": "No query results for model [Product] 1" }
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with('category')
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json([
            'product' => $product,
        ]);
    }

    /**
     * Create product (Admin)
     *
     * @authenticated
     *
     * @bodyParam category_id integer required Category ID. Example: 1
     * @bodyParam name string required Product name. Example: Rolex Submariner
     * @bodyParam description string optional Product description. Example: Luxury dive watch
     * @bodyParam price number required Product price. Example: 5000.00
     * @bodyParam stock integer required Stock quantity. Example: 10
     * @bodyParam brand string optional Brand name. Example: Rolex
     * @bodyParam image string optional Image filename. Example: rolex.jpg
     * @bodyParam is_active boolean optional Whether product is active. Example: true
     *
     * @response 201 {
     *   "message": "Product created successfully.",
     *   "product": {
     *     "id": 1,
     *     "name": "Rolex Submariner",
     *     "price": "5000.00",
     *     "category": { "id": 1, "name": "Watches" }
     *   }
     * }
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => __('messages.product_created'),
            'product' => $product->load('category'),
        ], 201);
    }

    /**
     * Update product (Admin)
     *
     * @authenticated
     *
     * @urlParam id integer required Product ID. Example: 1
     * @bodyParam category_id integer optional Category ID. Example: 1
     * @bodyParam name string optional Product name. Example: Rolex Submariner
     * @bodyParam price number optional Product price. Example: 4500.00
     * @bodyParam stock integer optional Stock quantity. Example: 8
     * @bodyParam is_active boolean optional Whether product is active. Example: true
     *
     * @response 200 {
     *   "message": "Product updated successfully.",
     *   "product": {}
     * }
     * @response 404 { "message": "No query results for model [Product] 1" }
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($request->validated());

        return response()->json([
            'message' => __('messages.product_updated'),
            'product' => $product->load('category'),
        ]);
    }

    /**
     * Delete product (Admin)
     *
     * @authenticated
     *
     * @urlParam id integer required Product ID. Example: 1
     *
     * @response 200 { "message": "Product deleted successfully." }
     * @response 404 { "message": "No query results for model [Product] 1" }
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => __('messages.product_deleted'),
        ]);
    }
}
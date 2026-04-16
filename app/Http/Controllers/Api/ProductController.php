<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * List active products with optional filters.
     * Query params: category_id, brand, min_price, max_price, search
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
     * Show a single product.
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
     * Create a new product (admin only).
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product->load('category'),
        ], 201);
    }

    /**
     * Update a product (admin only).
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($request->validated());

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->load('category'),
        ]);
    }

    /**
     * Delete a product (admin only).
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
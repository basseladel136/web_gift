<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Categories
 *
 * APIs for browsing and managing product categories.
 */
class CategoryController extends Controller
{
    /**
     * List all categories
     *
     * Returns all categories with their product count.
     *
     * @response 200 {
     *   "categories": [
     *     {
     *       "id": 1,
     *       "name": "Watches",
     *       "slug": "watches",
     *       "description": "Luxury watches",
     *       "products_count": 5
     *     }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Create a category (Admin)
     *
     * @authenticated
     *
     * @bodyParam name string required Category name. Example: Watches
     * @bodyParam description string optional Category description. Example: Luxury watches
     *
     * @response 201 {
     *   "message": "Category created successfully.",
     *   "category": {
     *     "id": 1,
     *     "name": "Watches",
     *     "slug": "watches",
     *     "description": "Luxury watches"
     *   }
     * }
     * @response 422 {
     *   "message": "The name field is required."
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $category = Category::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
        ]);

        return response()->json([
            'message'  => __('messages.category_created'),
            'category' => $category,
        ], 201);
    }
}
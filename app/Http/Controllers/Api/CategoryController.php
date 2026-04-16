<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * List all categories with their product count.
     */
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }
}
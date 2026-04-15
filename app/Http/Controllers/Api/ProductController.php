<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
public function index(Request $request)
{
    $query = Product::query();

    if ($request->category_id) {
        $query->where('category_id', $request->category_id);
    }

    if ($request->min_price) {
        $query->where('price', '>=', $request->min_price);
    }

    return $query->paginate(10);
}
public function show($id)
{
    return Product::with('reviews')->findOrFail($id);
}
}

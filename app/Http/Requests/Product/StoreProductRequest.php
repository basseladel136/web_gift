<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'       => ['required', 'integer', 'min:0'],
            'image'       => ['nullable', 'string', 'max:500'],
            'brand'       => ['nullable', 'string', 'max:100'],
            'is_active'   => ['boolean'],
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'category_id' => [
                'description' => 'Category ID.',
                'example'     => 1,
            ],
            'name' => [
                'description' => 'Product name.',
                'example'     => 'Rolex Submariner',
            ],
            'description' => [
                'description' => 'Product description.',
                'example'     => 'Luxury dive watch.',
            ],
            'price' => [
                'description' => 'Product price.',
                'example'     => 5000.00,
            ],
            'stock' => [
                'description' => 'Stock quantity.',
                'example'     => 10,
            ],
            'brand' => [
                'description' => 'Brand name.',
                'example'     => 'Rolex',
            ],
            'image' => [
                'description' => 'Image filename.',
                'example'     => 'rolex.jpg',
            ],
            'is_active' => [
                'description' => 'Whether product is active.',
                'example'     => true,
            ],
        ];
    }
}

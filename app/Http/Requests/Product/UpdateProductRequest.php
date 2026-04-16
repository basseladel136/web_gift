<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'stock'       => ['sometimes', 'integer', 'min:0'],
            'image'       => ['nullable', 'string', 'max:500'],
            'brand'       => ['nullable', 'string', 'max:100'],
            'is_active'   => ['boolean'],
        ];
    }
}
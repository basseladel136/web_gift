<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1'],
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'product_id' => [
                'description' => 'The product ID to add.',
                'example'     => 1,
            ],
            'quantity' => [
                'description' => 'Quantity to add.',
                'example'     => 2,
            ],
        ];
    }
}

<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartRequest extends FormRequest
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
                'description' => 'The product ID to update.',
                'example'     => 1,
            ],
            'quantity' => [
                'description' => 'New quantity.',
                'example'     => 3,
            ],
        ];
    }
}

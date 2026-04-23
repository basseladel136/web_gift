<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gift_message' => ['nullable', 'string', 'max:500'],
            'coupon_code'  => ['nullable', 'string', 'exists:coupons,code'],
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'gift_message' => [
                'description' => 'Optional gift message to include with the order.',
                'example'     => 'Happy Birthday!',
            ],
            'coupon_code' => [
                'description' => 'Optional coupon code to apply a discount.',
                'example'     => 'SAVE10',
            ],
        ];
    }
}

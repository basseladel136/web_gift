<?php

namespace App\Http\Requests\Coupon;

use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'             => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type'             => ['required', 'in:percent,fixed'],
            'value'            => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_uses'         => ['nullable', 'integer', 'min:1'],
            'is_active'        => ['boolean'],
            'expires_at'       => ['nullable', 'date', 'after:today'],
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'code' => [
                'description' => 'Unique coupon code.',
                'example'     => 'SAVE10',
            ],
            'type' => [
                'description' => 'Discount type: percent or fixed.',
                'example'     => 'percent',
            ],
            'value' => [
                'description' => 'Discount value.',
                'example'     => 10,
            ],
            'min_order_amount' => [
                'description' => 'Minimum order amount required.',
                'example'     => 100,
            ],
            'max_uses' => [
                'description' => 'Maximum number of uses.',
                'example'     => 50,
            ],
            'expires_at' => [
                'description' => 'Expiry date.',
                'example'     => '2026-12-31',
            ],
        ];
    }
}

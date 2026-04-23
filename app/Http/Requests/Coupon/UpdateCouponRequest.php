<?php

namespace App\Http\Requests\Coupon;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'             => ['sometimes', 'string', 'max:50', 'unique:coupons,code,' . $this->route('id')],
            'type'             => ['sometimes', 'in:percent,fixed'],
            'value'            => ['sometimes', 'numeric', 'min:0'],
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
                'description' => 'Coupon code.',
                'example'     => 'SAVE20',
            ],
            'type' => [
                'description' => 'Discount type: percent or fixed.',
                'example'     => 'fixed',
            ],
            'value' => [
                'description' => 'Discount value.',
                'example'     => 50,
            ],
            'is_active' => [
                'description' => 'Whether coupon is active.',
                'example'     => false,
            ],
            'expires_at' => [
                'description' => 'Expiry date.',
                'example'     => '2027-01-01',
            ],
        ];
    }
}

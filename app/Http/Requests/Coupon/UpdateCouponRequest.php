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
}

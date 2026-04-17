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
}
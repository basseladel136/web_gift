<?php

namespace App\Http\Requests\Coupon;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'code' => [
                'description' => 'Coupon code to apply.',
                'example'     => 'SAVE10',
            ],
        ];
    }
}

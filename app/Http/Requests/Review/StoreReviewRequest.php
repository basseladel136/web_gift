<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'rating' => [
                'description' => 'Rating from 1 to 5.',
                'example'     => 5,
            ],
            'comment' => [
                'description' => 'Optional review comment.',
                'example'     => 'Amazing product!',
            ],
        ];
    }
}

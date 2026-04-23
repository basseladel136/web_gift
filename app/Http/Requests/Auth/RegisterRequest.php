<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required.',
            'name.min' => 'Name must be at least 2 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password_confirmation.required' => 'Password confirmation is required.',
            'password_confirmation.min' => 'Password confirmation must be at least 8 characters.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The user\'s full name.',
                'example' => 'Bassel Adel',
            ],
            'email' => [
                'description' => 'The user\'s email address.',
                'example' => 'bassel@test.com',
            ],
            'password' => [
                'description' => 'The user\'s password (min 8 characters).',
                'example' => 'password123',
            ],
            'password_confirmation' => [
                'description' => 'Must match password.',
                'example' => 'password123',
            ],
        ];
    }
}

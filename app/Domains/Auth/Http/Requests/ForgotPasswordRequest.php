<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Requests;

use App\Domains\Auth\Rules\AllowedRedirectUrlRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password_reset_page_url' => ['required', 'url', 'max:2048', new AllowedRedirectUrlRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'An email address is required.',
            'email.email' => 'The email address is not valid.',
            'email.max' => 'The email address may not be longer than 255 characters.',
            'password_reset_page_url.required' => 'The password reset page URL is required.',
            'password_reset_page_url.url' => 'The password reset page URL is not a valid URL.',
            'password_reset_page_url.max' => 'The password reset page URL is too long.',
        ];
    }
}

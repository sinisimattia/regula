<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Requests;

use App\Domains\Auth\Rules\AllowedRedirectUrlRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendEmailVerificationNotificationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'after_verification_redirect_url' => ['required', 'url', 'max:2048', new AllowedRedirectUrlRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'after_verification_redirect_url.required' => 'The redirect URL is required.',
            'after_verification_redirect_url.url' => 'The redirect URL is not a valid URL.',
            'after_verification_redirect_url.max' => 'The redirect URL is too long.',
        ];
    }
}

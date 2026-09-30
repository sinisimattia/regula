<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Requests;

use App\Domains\Auth\Entities\User;
use App\Domains\Auth\Entities\UserAccount;
use App\Domains\Auth\Rules\AllowedRedirectUrlRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'password_confirmation' => ['required', 'same:password'],
            'terms' => ['required', 'accepted'],
            'preferred_language' => ['sometimes', 'nullable', 'string', 'max:2'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'after_verification_redirect_url' => ['sometimes', 'nullable', 'url', 'max:2048', new AllowedRedirectUrlRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'A first name is required.',
            'first_name.max' => 'The first name may not be longer than 255 characters.',
            'last_name.required' => 'A last name is required.',
            'last_name.max' => 'The last name may not be longer than 255 characters.',
            'email.required' => 'An email address is required.',
            'email.email' => 'The email address is not valid.',
            'email.max' => 'The email address may not be longer than 255 characters.',
            'password.required' => 'A password is required.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password_confirmation.required' => 'The password confirmation is required.',
            'password_confirmation.same' => 'The password confirmation does not match.',
            'terms.required' => 'The terms must be accepted.',
            'terms.accepted' => 'The terms must be accepted.',
            'preferred_language.max' => 'The preferred language must be a two-letter code.',
            'timezone.max' => 'The timezone may not be longer than 255 characters.',
            'after_verification_redirect_url.url' => 'The redirect URL is not a valid URL.',
            'after_verification_redirect_url.max' => 'The redirect URL is too long.',
        ];
    }

    /**
     * The account to register, without its password: the service hashes that separately.
     */
    public function extractEntity(): UserAccount
    {
        $firstName = (string) $this->input('first_name');
        $lastName = (string) $this->input('last_name');

        return new UserAccount(
            user: new User(
                displayName: trim($firstName . ' ' . $lastName) ?: null,
                email: (string) $this->input('email'),
            ),
            firstName: $firstName,
            lastName: $lastName,
            preferredLanguage: $this->input('preferred_language') ?? config('app.locale'),
            timezone: $this->input('timezone') ?? config('app.timezone'),
        );
    }
}

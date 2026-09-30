<?php

declare(strict_types=1);

use App\Domains\Auth\Enums\TokenAbility;
use App\Domains\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['guest', 'throttle:20,1'],
], function () {
    Route::post('login', [AuthController::class, 'login'])
        ->name('login');
    Route::post('register', [AuthController::class, 'register'])
        ->name('register');
    Route::post('forgot_password', [AuthController::class, 'forgotPassword'])
        ->name('forgot_password');
    Route::post('reset_password', [AuthController::class, 'resetPassword'])
        ->name('reset_password');
});

// The one route a refresh token reaches; every other authenticated route uses the `authenticated` group
Route::post('refresh_token', [AuthController::class, 'refreshToken'])
    ->middleware(['auth:sanctum', 'abilities:' . TokenAbility::IssueAccessToken->value])
    ->name('refresh_token');

Route::group([
    'middleware' => ['authenticated'],
], function () {
    Route::post('logout', [AuthController::class, 'logout'])
        ->name('logout');
    Route::post('send_verification_email', [AuthController::class, 'sendEmailVerificationNotification'])
        ->name('send_verification_email');
    Route::delete('delete_account', [AuthController::class, 'deleteAccount'])
        ->name('delete_account');
});

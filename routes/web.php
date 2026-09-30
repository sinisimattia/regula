<?php

declare(strict_types=1);

use App\Domains\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('verify/{userId}/{hash}', [AuthController::class, 'verifyAccount'])
    ->whereNumber('userId')
    ->middleware(['signed'])
    ->name('verification.verify');

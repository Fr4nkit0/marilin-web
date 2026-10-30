<?php

use App\Accounts\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Accounts\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Accounts\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Accounts\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Accounts\Http\Controllers\Auth\NewPasswordController;
use App\Accounts\Http\Controllers\Auth\PasswordController;
use App\Accounts\Http\Controllers\Auth\PasswordResetLinkController;
use App\Accounts\Http\Controllers\Auth\VerifyEmailController;
use App\Accounts\Http\Controllers\ProfileController;
use App\Accounts\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// There is no self sign up: the admin creates every account.
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Cuentas de repartidores
    Route::middleware('can:manage-users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/password', [UserController::class, 'password'])->name('users.password');
        Route::patch('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
    });
});

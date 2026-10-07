<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExchangeRateController;
use App\Http\Controllers\Admin\GameController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\MemberAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\EnsureAdminStaff;
use Illuminate\Support\Facades\Route;

// Topup Store Homepage
Route::get('/', [HomeController::class, 'index'])->name('home');

// Member Customer Authentication (Storefront diamond topup)
Route::post('/member/login', [MemberAuthController::class, 'login'])->name('member.login');

// Custom Vue Admin Dashboard & Management System (Strictly Admin Staff Only)
Route::middleware(['auth', EnsureAdminStaff::class])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Games Management
    Route::get('/games', [GameController::class, 'index'])->name('games.index');
    Route::post('/games', [GameController::class, 'store'])->name('games.store');
    Route::put('/games/{game}', [GameController::class, 'update'])->name('games.update');
    Route::delete('/games/{game}', [GameController::class, 'destroy'])->name('games.destroy');
    Route::patch('/games/{game}/toggle', [GameController::class, 'toggleStatus'])->name('games.toggle');

    // Products Management
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::patch('/products/{product}/toggle', [ProductController::class, 'toggleStatus'])->name('products.toggle');
    Route::post('/products/sync-tokovoucher', [ProductController::class, 'syncTokovoucher'])->name('products.sync-tokovoucher');

    // Orders Management
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::post('/orders/{order}/retry-tokovoucher', [OrderController::class, 'retryTokovoucher'])->name('orders.retry-tokovoucher');
    Route::post('/orders/{order}/check-tokovoucher', [OrderController::class, 'checkTokovoucherStatus'])->name('orders.check-tokovoucher');

    // Currencies & Exchange Rates Management
    Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
    Route::post('/exchange-rates', [ExchangeRateController::class, 'store'])->name('exchange-rates.store');
    Route::put('/exchange-rates/{currency}', [ExchangeRateController::class, 'update'])->name('exchange-rates.update');
    Route::delete('/exchange-rates/{currency}', [ExchangeRateController::class, 'destroy'])->name('exchange-rates.destroy');

    // Users Management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::patch('/users/{user}/toggle', [UserController::class, 'toggleStatus'])->name('users.toggle');
});

// Admin root redirect
Route::get('/admin', fn () => redirect()->route('admin.dashboard'));

// Redirect legacy /dashboard
Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'))->name('dashboard');

// Google Authentication Routes (Option 1 for Member)
Route::get('/auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::post('/auth/google/quick-signin', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'quickGoogleLogin'])->name('auth.google.quick');

// Telegram Authentication Routes (Option 2 for Member)
Route::post('/auth/telegram/phone', [\App\Http\Controllers\Auth\TelegramAuthController::class, 'loginWithPhone'])->name('auth.telegram.phone');
Route::get('/auth/telegram/callback', [\App\Http\Controllers\Auth\TelegramAuthController::class, 'callback'])->name('auth.telegram.callback');

require __DIR__.'/auth.php';

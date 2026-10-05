<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Topup Store Homepage
Route::get('/', [HomeController::class, 'index'])->name('home');

// Redirect old Breeze dashboard to Filament admin dashboard
Route::redirect('/dashboard', '/admin/dashboard')->name('dashboard');

require __DIR__.'/auth.php';

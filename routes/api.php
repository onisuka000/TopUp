<?php

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Endpoint បង្កើត Order និងទាញយក KHQR Code
Route::post('/orders/create', [OrderController::class, 'create']);

// Endpoint ឆែកស្ថានភាពបង់ប្រាក់ (សម្រាប់ Polling ក្នុង Modal)
Route::get('/orders/{orderNumber}/status', [OrderController::class, 'getStatus']);

// Webhook Endpoint សម្រាប់ទទួល Callback ពីធនាគារ
Route::post('/webhook/aba', [WebhookController::class, 'handleAbaWebhook']);

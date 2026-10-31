<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Store APIs
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:60,1')->group(function () {
    // Katalog Produk Toko
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    // Checkout & Order Tracking
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);

    // Payment Gateway Webhook
    Route::post('/payment/midtrans-webhook', [OrderController::class, 'midtransWebhook']);

    // Serve public storage images via API route
    Route::get('/storage/{path}', function ($path) {
        $fullPath = storage_path('app/public/' . $path);
        if (!file_exists($fullPath)) {
            abort(404);
        }

        $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';
        return response()->file($fullPath, ['Content-Type' => $mimeType]);
    })->where('path', '.*');
});

/*
|--------------------------------------------------------------------------
| Auth Customer / Admin
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:login')->post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Admin Store Routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/products', [ProductController::class, 'adminIndex']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::match(['put', 'post'], '/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    });
});

<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OwnerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShowcaseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Store APIs
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:60,1')->group(function () {
    // Kategori
    Route::get('/categories', [CategoryController::class, 'index']);

    // Etalase (Showcases)
    Route::get('/showcases', [ShowcaseController::class, 'index']);
    Route::get('/showcases/{slug}', [ShowcaseController::class, 'show']);

    // Katalog Produk Toko
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    // Checkout & Order Tracking
    Route::middleware('throttle:checkout')->group(function () {
        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::post('/orders/checkout', [OrderController::class, 'checkout']);
    });
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);

    // Payment Gateway Webhook
    Route::post('/payment/midtrans-webhook', [OrderController::class, 'midtransWebhook']);

    // Serve public storage images via API route
    Route::get('/storage/{path}', function ($path) {
        // Path traversal & file extension hardening against php2shell / web shell
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $cleanPath = ltrim(str_replace(['../', '..\\'], '', $path), '/');
        $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            abort(403, 'Akses file dibatasi.');
        }

        $fullPath = storage_path('app/public/' . $cleanPath);
        if (!file_exists($fullPath)) {
            abort(404);
        }

        $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';
        return response()->file($fullPath, ['Content-Type' => $mimeType]);
        })->where('path', '.*');
});

/*
|--------------------------------------------------------------------------
| Auth Customer / Admin / Owner
|--------------------------------------------------------------------------
*/
Route::get('/auth/captcha', [AuthController::class, 'getCaptcha']);
Route::middleware('throttle:otp_burst')->post('/auth/request-otp', [AuthController::class, 'requestOtp']);
Route::middleware('throttle:register')->post('/auth/register', [AuthController::class, 'register']);
Route::middleware('throttle:login')->post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Admin & Owner Shared Capabilities
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Produk Etalase
        Route::get('/products', [ProductController::class, 'adminIndex']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::match(['put', 'post'], '/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        // Kategori
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Etalase Toko (Showcases)
        Route::get('/showcases', [ShowcaseController::class, 'adminIndex']);
        Route::post('/showcases', [ShowcaseController::class, 'store']);
        Route::put('/showcases/{id}', [ShowcaseController::class, 'update']);
        Route::delete('/showcases/{id}', [ShowcaseController::class, 'destroy']);

        // Order Tracking
        Route::get('/orders', [OrderController::class, 'adminIndex']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    });

    // Owner Only Capabilities (Highest Hierarchy)
    Route::middleware('owner')->prefix('owner')->group(function () {
        Route::get('/admins', [OwnerController::class, 'indexAdmins']);
        Route::post('/admins', [OwnerController::class, 'storeAdmin']);
        Route::put('/admins/{id}', [OwnerController::class, 'updateAdmin']);
        Route::delete('/admins/{id}', [OwnerController::class, 'destroyAdmin']);
    });
});

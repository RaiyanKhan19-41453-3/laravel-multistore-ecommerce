<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShippingController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/check-email', [AuthController::class, 'checkEmail'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/cart/merge', [CartController::class, 'merge']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::patch('/addresses/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
});

Route::middleware(['api.cart', 'throttle:30,1'])->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'store']);
});

Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/orders/lookup', [OrderController::class, 'lookup']);
});

Route::middleware(['api.cart', 'throttle:30,1'])->group(function () {
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'updateQuantity']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem']);
    Route::post('/cart/coupon', [CartController::class, 'applyCoupon']);
    Route::delete('/cart/coupon', [CartController::class, 'removeCoupon']);
    Route::delete('/cart', [CartController::class, 'clear']);
});

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);

Route::get('/brands', [BrandController::class, 'index']);
Route::get('/brands/{slug}', [BrandController::class, 'show']);

Route::get('/shipping/cities', [ShippingController::class, 'cities']);
Route::get('/shipping/rates', [ShippingController::class, 'rates']);

Route::post('/payments/webhook/{method}', [PaymentWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('payments.webhook.sslcommerz');

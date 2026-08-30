<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('store/index'))->name('home');

Route::get('/products/{slug}', fn (string $slug) => Inertia::render('store/products/show', [
    'slug' => $slug,
]))->name('store.product');

Route::get('/account/login', fn () => Inertia::render('account/login'))->name('store.login');
Route::get('/account/register', fn () => Inertia::render('account/register'))->name('store.register');
Route::get('/cart', fn () => Inertia::render('account/cart'))->name('store.cart');

Route::get('/products', fn () => Inertia::render('store/products/index'))->name('store.products');

Route::get('/checkout', fn () => Inertia::render('account/checkout'))->name('store.checkout');
Route::get('/order-confirmation/{order_number?}', fn (?string $order_number) => Inertia::render('account/order-confirmation', [
    'orderNumber' => $order_number,
]))->name('store.order-confirmation');

Route::middleware(['auth', 'role:super-admin'])->group(function () {
    Route::get('admin/dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Store\ProductController as StoreProductController;
use App\Http\Controllers\Store\StorefrontController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/', [StorefrontController::class, 'home'])->name('home');

Route::get('/products/{slug}', [StorefrontController::class, 'product'])->name('store.product');

Route::get('/products', [StoreProductController::class, 'index'])->name('store.products');

Route::get('/categories/{slug}', [StorefrontController::class, 'category'])->name('store.category');

Route::get('/brands/{slug}', [StorefrontController::class, 'brand'])->name('store.brand');

Route::get('/search', fn () => Inertia::render('store/search'))->name('store.search');

Route::get('/pages/{slug}', fn (string $slug) => Inertia::render('store/pages/show', [
    'slug' => $slug,
]))->name('store.page');

Route::get('/wishlist', fn () => Inertia::render('wishlist/index'))->name('store.wishlist');

Route::get('/account', fn () => Inertia::render('account/index'))->name('store.account');
Route::get('/account/orders', fn () => Inertia::render('account/orders/index'))->name('store.account.orders');
Route::get('/account/orders/{order}', fn (int $order) => Inertia::render('account/orders/show', [
    'orderId' => $order,
]))->name('store.account.order');
Route::get('/account/addresses', fn () => Inertia::render('account/addresses/index'))->name('store.account.addresses');
Route::get('/account/login', fn () => Inertia::render('account/login'))->name('store.login');
Route::get('/account/register', fn () => Inertia::render('account/register'))->name('store.register');
Route::get('/account/forgot-password', fn () => Inertia::render('account/forgot-password'))->name('store.forgot-password');
Route::get('/account/reset-password', fn () => Inertia::render('account/reset-password'))->name('store.reset-password');
Route::get('/cart', fn () => Inertia::render('account/cart'))->name('store.cart');

Route::get('/checkout', fn () => Inertia::render('account/checkout'))->name('store.checkout');
Route::get('/order-confirmation/{order_number?}', fn (?string $order_number = null) => Inertia::render('account/order-confirmation', [
    'orderNumber' => $order_number,
]))->name('store.order-confirmation');

Route::middleware(['auth', 'permission:reports.view|orders.view|catalog.view'])->group(function () {
    Route::get('admin/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

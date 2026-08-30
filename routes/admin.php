<?php

use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\StoreActivityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::post('categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
    Route::put('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
    Route::post('brands/{brand}/toggle', [BrandController::class, 'toggle'])->name('brands.toggle');
    Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

    Route::get('attributes', [AttributeController::class, 'index'])->name('attributes.index');
    Route::post('attributes', [AttributeController::class, 'store'])->name('attributes.store');
    Route::put('attributes/{attribute}', [AttributeController::class, 'update'])->name('attributes.update');
    Route::post('attributes/{attribute}/toggle', [AttributeController::class, 'toggle'])->name('attributes.toggle');
    Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy'])->name('attributes.destroy');

    Route::post('attributes/{attribute}/values', [AttributeController::class, 'storeValue'])->name('attributes.values.store');
    Route::put('attributes/{attribute}/values/{value}', [AttributeController::class, 'updateValue'])->name('attributes.values.update');
    Route::post('attributes/{attribute}/values/{value}/toggle', [AttributeController::class, 'toggleValue'])->name('attributes.values.toggle');
    Route::delete('attributes/{attribute}/values/{value}', [AttributeController::class, 'destroyValue'])->name('attributes.values.destroy');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::post('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');
    Route::post('products/{product}/toggle-featured', [ProductController::class, 'toggleFeatured'])->name('products.toggle-featured');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('products/{product}/images', [ProductImageController::class, 'index'])->name('products.images.index');
    Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
    Route::put('products/{product}/images/reorder', [ProductImageController::class, 'reorder'])->name('products.images.reorder');
    Route::delete('products/{product}/images/bulk', [ProductImageController::class, 'bulkDestroy'])->name('products.images.bulk-destroy');
    Route::put('products/{product}/images/{image}', [ProductImageController::class, 'update'])->name('products.images.update');
    Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');

    Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])->name('products.variants.index');
    Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->name('products.variants.store');
    Route::put('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('products.variants.update');
    Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('products.variants.destroy');
    Route::post('products/{product}/variants/generate', [ProductVariantController::class, 'generate'])->name('products.variants.generate');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::get('inventory/{inventory}/movements', [InventoryController::class, 'movements'])->name('inventory.movements');

    Route::get('discounts', [DiscountController::class, 'index'])->name('discounts.index');
    Route::get('discounts/create', [DiscountController::class, 'create'])->name('discounts.create');
    Route::post('discounts', [DiscountController::class, 'store'])->name('discounts.store');
    Route::get('discounts/{discount}', [DiscountController::class, 'show'])->name('discounts.show');
    Route::get('discounts/{discount}/edit', [DiscountController::class, 'edit'])->name('discounts.edit');
    Route::put('discounts/{discount}', [DiscountController::class, 'update'])->name('discounts.update');
    Route::post('discounts/{discount}/toggle', [DiscountController::class, 'toggle'])->name('discounts.toggle');
    Route::delete('discounts/{discount}', [DiscountController::class, 'destroy'])->name('discounts.destroy');

    Route::post('discounts/{discount}/coupons', [CouponController::class, 'store'])->name('discounts.coupons.store');
    Route::put('discounts/{discount}/coupons/{coupon}', [CouponController::class, 'update'])->name('discounts.coupons.update');
    Route::post('discounts/{discount}/coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('discounts.coupons.toggle');
    Route::delete('discounts/{discount}/coupons/{coupon}', [CouponController::class, 'destroy'])->name('discounts.coupons.destroy');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('store-activity', [StoreActivityController::class, 'index'])->name('store-activity.index');
    Route::post('store-activity/clear-all-carts', [StoreActivityController::class, 'clearAllCarts'])->name('store-activity.clear-all-carts');

    Route::get('shipping', [ShippingController::class, 'index'])->name('shipping.index');
    Route::post('shipping/methods', [ShippingController::class, 'storeMethod'])->name('shipping.methods.store');
    Route::put('shipping/methods/{method}', [ShippingController::class, 'updateMethod'])->name('shipping.methods.update');
    Route::delete('shipping/methods/{method}', [ShippingController::class, 'destroyMethod'])->name('shipping.methods.destroy');
    Route::post('shipping/zones', [ShippingController::class, 'storeZone'])->name('shipping.zones.store');
    Route::put('shipping/zones/{zone}', [ShippingController::class, 'updateZone'])->name('shipping.zones.update');
    Route::delete('shipping/zones/{zone}', [ShippingController::class, 'destroyZone'])->name('shipping.zones.destroy');
    Route::post('shipping/rates', [ShippingController::class, 'storeRate'])->name('shipping.rates.store');
    Route::delete('shipping/rates/{rate}', [ShippingController::class, 'destroyRate'])->name('shipping.rates.destroy');
});

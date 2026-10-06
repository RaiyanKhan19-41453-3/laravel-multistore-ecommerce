<?php

use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CourierController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\HomepageController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlatformStoreController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\ProductLabelController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\StoreActivityController;
use App\Http\Controllers\Admin\StoreContextController;
use App\Http\Controllers\Admin\StoreMemberController;
use App\Http\Controllers\Admin\StoreProfileController;
use App\Http\Controllers\Admin\ZatcaController;
use App\Http\Middleware\EnsureAdminStoreAccess;
use App\Http\Middleware\EnsureStoreSubscription;
use App\Http\Middleware\LogAdminActivity;
use App\Http\Middleware\ResolveAdminStore;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ResolveAdminStore::class, EnsureAdminStoreAccess::class, EnsureStoreSubscription::class, LogAdminActivity::class])->prefix('admin')->name('admin.')->group(function () {
    $catalogRead = 'permission:catalog.view|catalog.manage';
    $catalogWrite = 'permission:catalog.manage';
    $orderRead = 'permission:orders.view|orders.manage';
    $orderWrite = 'permission:orders.manage';

    Route::get('categories', [CategoryController::class, 'index'])->middleware($catalogRead)->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->middleware($catalogWrite)->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware($catalogWrite)->name('categories.update');
    Route::post('categories/{category}/toggle', [CategoryController::class, 'toggle'])->middleware($catalogWrite)->name('categories.toggle');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware($catalogWrite)->name('categories.destroy');

    Route::get('brands', [BrandController::class, 'index'])->middleware($catalogRead)->name('brands.index');
    Route::post('brands', [BrandController::class, 'store'])->middleware($catalogWrite)->name('brands.store');
    Route::put('brands/{brand}', [BrandController::class, 'update'])->middleware($catalogWrite)->name('brands.update');
    Route::post('brands/{brand}/toggle', [BrandController::class, 'toggle'])->middleware($catalogWrite)->name('brands.toggle');
    Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->middleware($catalogWrite)->name('brands.destroy');

    Route::get('attributes', [AttributeController::class, 'index'])->middleware($catalogRead)->name('attributes.index');
    Route::post('attributes', [AttributeController::class, 'store'])->middleware($catalogWrite)->name('attributes.store');
    Route::put('attributes/{attribute}', [AttributeController::class, 'update'])->middleware($catalogWrite)->name('attributes.update');
    Route::post('attributes/{attribute}/toggle', [AttributeController::class, 'toggle'])->middleware($catalogWrite)->name('attributes.toggle');
    Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy'])->middleware($catalogWrite)->name('attributes.destroy');

    Route::post('attributes/{attribute}/values', [AttributeController::class, 'storeValue'])->middleware($catalogWrite)->name('attributes.values.store');
    Route::put('attributes/{attribute}/values/{value}', [AttributeController::class, 'updateValue'])->middleware($catalogWrite)->name('attributes.values.update');
    Route::post('attributes/{attribute}/values/{value}/toggle', [AttributeController::class, 'toggleValue'])->middleware($catalogWrite)->name('attributes.values.toggle');
    Route::delete('attributes/{attribute}/values/{value}', [AttributeController::class, 'destroyValue'])->middleware($catalogWrite)->name('attributes.values.destroy');

    Route::get('products', [ProductController::class, 'index'])->middleware($catalogRead)->name('products.index');
    Route::get('products/labels', [ProductLabelController::class, 'index'])->middleware($catalogRead)->name('products.labels');
    Route::get('products/labels/print', [ProductLabelController::class, 'print'])->middleware($catalogRead)->name('products.labels.print');
    Route::get('products/create', [ProductController::class, 'create'])->middleware($catalogWrite)->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->middleware($catalogWrite)->name('products.store');
    Route::get('products/{product}', [ProductController::class, 'show'])->middleware($catalogRead)->name('products.show');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->middleware($catalogWrite)->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->middleware($catalogWrite)->name('products.update');
    Route::post('products/{product}/toggle', [ProductController::class, 'toggle'])->middleware($catalogWrite)->name('products.toggle');
    Route::post('products/{product}/toggle-featured', [ProductController::class, 'toggleFeatured'])->middleware($catalogWrite)->name('products.toggle-featured');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware($catalogWrite)->name('products.destroy');

    Route::get('products/{product}/images', [ProductImageController::class, 'index'])->middleware($catalogRead)->name('products.images.index');
    Route::post('products/{product}/images', [ProductImageController::class, 'store'])->middleware($catalogWrite)->name('products.images.store');
    Route::put('products/{product}/images/reorder', [ProductImageController::class, 'reorder'])->middleware($catalogWrite)->name('products.images.reorder');
    Route::delete('products/{product}/images/bulk', [ProductImageController::class, 'bulkDestroy'])->middleware($catalogWrite)->name('products.images.bulk-destroy');
    Route::put('products/{product}/images/{image}', [ProductImageController::class, 'update'])->middleware($catalogWrite)->name('products.images.update');
    Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->middleware($catalogWrite)->name('products.images.destroy');

    Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])->middleware($catalogRead)->name('products.variants.index');
    Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->middleware($catalogWrite)->name('products.variants.store');
    Route::put('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->middleware($catalogWrite)->name('products.variants.update');
    Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->middleware($catalogWrite)->name('products.variants.destroy');
    Route::post('products/{product}/variants/generate', [ProductVariantController::class, 'generate'])->middleware($catalogWrite)->name('products.variants.generate');

    Route::get('inventory', [InventoryController::class, 'index'])->middleware('permission:inventory')->name('inventory.index');
    Route::post('inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory')->name('inventory.adjust');
    Route::get('inventory/{inventory}/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory')->name('inventory.movements');

    Route::get('discounts', [DiscountController::class, 'index'])->middleware('permission:discounts')->name('discounts.index');
    Route::get('discounts/create', [DiscountController::class, 'create'])->middleware('permission:discounts')->name('discounts.create');
    Route::post('discounts', [DiscountController::class, 'store'])->middleware('permission:discounts')->name('discounts.store');
    Route::get('discounts/{discount}', [DiscountController::class, 'show'])->middleware('permission:discounts')->name('discounts.show');
    Route::get('discounts/{discount}/edit', [DiscountController::class, 'edit'])->middleware('permission:discounts')->name('discounts.edit');
    Route::put('discounts/{discount}', [DiscountController::class, 'update'])->middleware('permission:discounts')->name('discounts.update');
    Route::post('discounts/{discount}/toggle', [DiscountController::class, 'toggle'])->middleware('permission:discounts')->name('discounts.toggle');
    Route::delete('discounts/{discount}', [DiscountController::class, 'destroy'])->middleware('permission:discounts')->name('discounts.destroy');

    Route::post('discounts/{discount}/coupons', [CouponController::class, 'store'])->middleware('permission:discounts')->name('discounts.coupons.store');
    Route::put('discounts/{discount}/coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:discounts')->name('discounts.coupons.update');
    Route::post('discounts/{discount}/coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->middleware('permission:discounts')->name('discounts.coupons.toggle');
    Route::delete('discounts/{discount}/coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:discounts')->name('discounts.coupons.destroy');

    Route::get('orders', [OrderController::class, 'index'])->middleware($orderRead)->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->middleware($orderRead)->name('orders.show');
    Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware($orderWrite)->name('orders.update-status');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware($orderWrite)->name('orders.cancel');
    Route::post('orders/{order}/shipments', [OrderController::class, 'storeShipment'])->middleware($orderWrite)->name('orders.shipments.store');
    Route::post('orders/{order}/send-to-courier', [OrderController::class, 'sendToCourier'])->middleware($orderWrite)->name('orders.send-to-courier');
    Route::put('orders/{order}/shipments/{shipment}', [OrderController::class, 'updateShipment'])->middleware($orderWrite)->name('orders.shipments.update');
    Route::delete('orders/{order}/shipments/{shipment}', [OrderController::class, 'destroyShipment'])->middleware($orderWrite)->name('orders.shipments.destroy');

    Route::get('orders/{order}/invoice', [InvoiceController::class, 'show'])->middleware($orderRead)->name('invoices.show');
    Route::get('orders/{order}/invoice/print', [InvoiceController::class, 'print'])->middleware($orderRead)->name('invoices.print');
    Route::get('orders/{order}/invoice/pdf', [InvoiceController::class, 'pdf'])->middleware($orderRead)->name('invoices.pdf');
    Route::get('orders/{order}/packing-slip', [InvoiceController::class, 'packingSlip'])->middleware($orderRead)->name('invoices.packing');

    Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers')->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers')->name('customers.show');

    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:reports')->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->middleware('permission:reports')->name('reports.export');

    Route::get('store-activity', [StoreActivityController::class, 'index'])->middleware('permission:activity')->name('store-activity.index');
    Route::post('store-activity/clear-all-carts', [StoreActivityController::class, 'clearAllCarts'])->middleware('role:super-admin')->name('store-activity.clear-all-carts');

    Route::get('store-context', [StoreContextController::class, 'show'])->name('store-context.show');
    Route::post('store-context', [StoreContextController::class, 'store'])->name('store-context.store');
    Route::delete('store-context', [StoreContextController::class, 'destroy'])->name('store-context.destroy');

    Route::get('store-members', [StoreMemberController::class, 'index'])->name('store-members.index');
    Route::post('store-members', [StoreMemberController::class, 'store'])->name('store-members.store');
    Route::delete('store-members/{user}', [StoreMemberController::class, 'destroy'])->name('store-members.destroy');

    Route::get('billing', [BillingController::class, 'show'])->name('billing.show');
    Route::post('billing/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');

    Route::get('plans', [PlanController::class, 'index'])->middleware('permission:stores')->name('plans.index');
    Route::post('plans', [PlanController::class, 'store'])->middleware('permission:stores')->name('plans.store');
    Route::get('plans/{plan}', [PlanController::class, 'show'])->middleware('permission:stores')->name('plans.show');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->middleware('permission:stores')->name('plans.update');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:stores')->name('plans.destroy');

    Route::get('stores', [PlatformStoreController::class, 'index'])->middleware('permission:stores')->name('stores.index');
    Route::get('stores/{store}', [PlatformStoreController::class, 'show'])->middleware('permission:stores')->name('stores.show');
    Route::post('stores/{store}/subscription', [PlatformStoreController::class, 'updateSubscription'])->middleware('permission:stores')->name('stores.subscription');
    Route::post('stores/{store}/toggle', [PlatformStoreController::class, 'toggle'])->middleware('permission:stores')->name('stores.toggle');

    Route::get('shipping', [ShippingController::class, 'index'])->middleware('permission:shipping')->name('shipping.index');
    Route::post('shipping/methods', [ShippingController::class, 'storeMethod'])->middleware('permission:shipping')->name('shipping.methods.store');
    Route::put('shipping/methods/{method}', [ShippingController::class, 'updateMethod'])->middleware('permission:shipping')->name('shipping.methods.update');
    Route::delete('shipping/methods/{method}', [ShippingController::class, 'destroyMethod'])->middleware('permission:shipping')->name('shipping.methods.destroy');
    Route::post('shipping/zones', [ShippingController::class, 'storeZone'])->middleware('permission:shipping')->name('shipping.zones.store');
    Route::put('shipping/zones/{zone}', [ShippingController::class, 'updateZone'])->middleware('permission:shipping')->name('shipping.zones.update');
    Route::delete('shipping/zones/{zone}', [ShippingController::class, 'destroyZone'])->middleware('permission:shipping')->name('shipping.zones.destroy');
    Route::post('shipping/rates', [ShippingController::class, 'storeRate'])->middleware('permission:shipping')->name('shipping.rates.store');
    Route::delete('shipping/rates/{rate}', [ShippingController::class, 'destroyRate'])->middleware('permission:shipping')->name('shipping.rates.destroy');

    // Couriers are code/config-managed (config/couriers.php): read-only here.
    Route::get('couriers', [CourierController::class, 'index'])->middleware('permission:shipping')->name('couriers.index');
    Route::post('couriers/{courierCode}/test-connection', [CourierController::class, 'testConnection'])->middleware('permission:shipping')->name('couriers.test-connection');

    Route::get('zatca', [ZatcaController::class, 'index'])->middleware('permission:zatca')->name('zatca.index');
    Route::post('zatca/documents/{document}/retry', [ZatcaController::class, 'retry'])->middleware('role:super-admin')->name('zatca.retry');

    Route::get('settings', [SettingController::class, 'index'])->middleware('permission:settings')->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->middleware('permission:settings')->name('settings.update');
    Route::post('settings/preset', [SettingController::class, 'applyPreset'])->middleware('permission:settings')->name('settings.preset');

    Route::get('store-profile', [StoreProfileController::class, 'index'])->middleware('permission:settings')->name('store-profile.index');
    Route::put('store-profile', [StoreProfileController::class, 'update'])->middleware('permission:settings')->name('store-profile.update');

    Route::get('reviews', [ReviewController::class, 'index'])->middleware('permission:reviews')->name('reviews.index');
    Route::post('reviews/{review}/approve', [ReviewController::class, 'approve'])->middleware('permission:reviews')->name('reviews.approve');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->middleware('permission:reviews')->name('reviews.destroy');

    Route::get('pages', [CmsPageController::class, 'index'])->middleware('permission:pages')->name('pages.index');
    Route::get('pages/create', [CmsPageController::class, 'create'])->middleware('permission:pages')->name('pages.create');
    Route::post('pages', [CmsPageController::class, 'store'])->middleware('permission:pages')->name('pages.store');
    Route::get('pages/{page}/edit', [CmsPageController::class, 'edit'])->middleware('permission:pages')->name('pages.edit');
    Route::put('pages/{page}', [CmsPageController::class, 'update'])->middleware('permission:pages')->name('pages.update');
    Route::post('pages/{page}/toggle', [CmsPageController::class, 'toggle'])->middleware('permission:pages')->name('pages.toggle');
    Route::delete('pages/{page}', [CmsPageController::class, 'destroy'])->middleware('permission:pages')->name('pages.destroy');

    Route::get('menus', [MenuController::class, 'index'])->middleware('permission:menus')->name('menus.index');
    Route::post('menus', [MenuController::class, 'store'])->middleware('permission:menus')->name('menus.store');
    Route::put('menus/{menu}', [MenuController::class, 'update'])->middleware('permission:menus')->name('menus.update');
    Route::post('menus/{menu}/toggle', [MenuController::class, 'toggle'])->middleware('permission:menus')->name('menus.toggle');
    Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->middleware('permission:menus')->name('menus.destroy');

    Route::get('homepage', [HomepageController::class, 'index'])->middleware('permission:homepage')->name('homepage.index');
    Route::put('homepage', [HomepageController::class, 'update'])->middleware('permission:homepage')->name('homepage.update');
    Route::post('homepage/slides', [HomepageController::class, 'storeSlide'])->middleware('permission:homepage')->name('homepage.slides.store');
    Route::put('homepage/slides/{slide}', [HomepageController::class, 'updateSlide'])->middleware('permission:homepage')->name('homepage.slides.update');
    Route::post('homepage/slides/{slide}/toggle', [HomepageController::class, 'toggleSlide'])->middleware('permission:homepage')->name('homepage.slides.toggle');
    Route::delete('homepage/slides/{slide}', [HomepageController::class, 'destroySlide'])->middleware('permission:homepage')->name('homepage.slides.destroy');

    Route::post('banners/fetch-embed', [HomepageController::class, 'fetchEmbed'])->middleware('permission:homepage')->name('banners.fetch-embed');
    Route::post('banners', [HomepageController::class, 'storeBanner'])->middleware('permission:homepage')->name('banners.store');
    Route::put('banners/{banner}', [HomepageController::class, 'updateBanner'])->middleware('permission:homepage')->name('banners.update');
    Route::post('banners/{banner}/toggle', [HomepageController::class, 'toggleBanner'])->middleware('permission:homepage')->name('banners.toggle');
    Route::delete('banners/{banner}', [HomepageController::class, 'destroyBanner'])->middleware('permission:homepage')->name('banners.destroy');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('role:super-admin')->name('audit-logs.index');
});

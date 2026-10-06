<?php

namespace App\Providers;

use App\Models\Address;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Discount;
use App\Models\HeroSlide;
use App\Models\HomepageSection;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\Wishlist;
use App\Models\ZatcaDevice;
use App\Models\ZatcaDocument;
use App\Observers\BrandObserver;
use App\Observers\CategoryObserver;
use App\Observers\CmsPageObserver;
use App\Observers\InventoryObserver;
use App\Observers\ProductObserver;
use App\Support\AdminStoreContext;
use App\Support\CurrentStore;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentStore::class);
        $this->app->singleton(AdminStoreContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        Category::observe(CategoryObserver::class);
        Brand::observe(BrandObserver::class);
        CmsPage::observe(CmsPageObserver::class);
        Inventory::observe(InventoryObserver::class);

        $this->autoFillStoreId();
    }

    /**
     * Auto-fill store_id for new tenant rows from the resolved store.
     *
     * Centralized here (instead of a trait on 28 models) so Phase 1 stays
     * additive: legacy rows with null keep working, new rows adopt the
     * current/default store. Never throws; never overwrites explicit values.
     */
    private function autoFillStoreId(): void
    {
        $models = [
            Product::class,
            ProductVariant::class,
            Attribute::class,
            AttributeValue::class,
            Category::class,
            Brand::class,
            ProductImage::class,
            Inventory::class,
            InventoryMovement::class,
            Cart::class,
            CartItem::class,
            Order::class,
            OrderItem::class,
            Address::class,
            Payment::class,
            Shipment::class,
            Discount::class,
            Coupon::class,
            CouponRedemption::class,
            ShippingMethod::class,
            ShippingZone::class,
            ShippingRate::class,
            CmsPage::class,
            MenuItem::class,
            HomepageSection::class,
            HeroSlide::class,
            Banner::class,
            Review::class,
            Wishlist::class,
            ZatcaDevice::class,
            ZatcaDocument::class,
        ];

        foreach ($models as $model) {
            if (! class_exists($model)) {
                continue;
            }

            $model::creating(function ($record) {
                try {
                    if (! empty($record->store_id)) {
                        return;
                    }

                    if (! self::hasStoreIdColumn($record->getTable())) {
                        return;
                    }

                    $id = app(CurrentStore::class)->id()
                        ?? app(CurrentStore::class)->default()?->id;

                    if ($id) {
                        $record->store_id = $id;
                    }
                } catch (\Throwable) {
                    // Never block creation when store context is unavailable.
                }
            });
        }
    }

    /**
     * Per-table schema cache: the schema cannot change mid-request, so a
     * single information_schema lookup per table avoids N+1 queries when
     * seeding or bulk-creating tenant rows.
     */
    private static function hasStoreIdColumn(string $table): bool
    {
        static $cache = [];

        if (! array_key_exists($table, $cache)) {
            try {
                $cache[$table] = Schema::hasColumn($table, 'store_id');
            } catch (\Throwable) {
                return false;
            }
        }

        return $cache[$table];
    }
}

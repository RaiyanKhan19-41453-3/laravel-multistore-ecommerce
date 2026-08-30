<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'value',
        'max_discount_amount',
        'minimum_order_amount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'usage_count',
        'is_active',
        'priority',
        'stackable',
        'coupon_only',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'stackable' => 'boolean',
            'coupon_only' => 'boolean',
        ];
    }

    public function toArray(): array
    {
        $data = parent::toArray();

        if (isset($data['product_variants'])) {
            $data['productVariants'] = $data['product_variants'];
            unset($data['product_variants']);
        }

        return $data;
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'discountable');
    }

    public function productVariants(): MorphToMany
    {
        return $this->morphedByMany(ProductVariant::class, 'discountable');
    }

    public function categories(): MorphToMany
    {
        return $this->morphedByMany(Category::class, 'discountable');
    }

    public function brands(): MorphToMany
    {
        return $this->morphedByMany(Brand::class, 'discountable');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function isActiveNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function getEffectiveDiscount(float $subtotal): float
    {
        if ($subtotal <= 0 || ! $this->isActiveNow()) {
            return 0;
        }

        if ($this->minimum_order_amount !== null && $subtotal < $this->minimum_order_amount) {
            return 0;
        }

        $discount = match ($this->type) {
            'percentage' => $subtotal * ($this->value / 100),
            'fixed' => $this->value,
            default => 0,
        };

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return round(min($discount, $subtotal), 2);
    }
}

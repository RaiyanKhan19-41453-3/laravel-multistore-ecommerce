<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToStore);
    }

    protected $fillable = [
        'shipping_method_id',
        'shipping_zone_id',
        'price',
        'free_shipping_min',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'free_shipping_min' => 'decimal:2',
        ];
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function getShippingCost(float $orderTotal): float
    {
        if ($this->free_shipping_min !== null && $orderTotal >= $this->free_shipping_min) {
            return 0;
        }

        return (float) $this->price;
    }
}

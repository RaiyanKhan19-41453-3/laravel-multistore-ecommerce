<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'quantity',
        'reserved_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    private const LOW_STOCK_THRESHOLD = 5;

    public function getAvailableQuantity(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }

    public function getIsInStock(): bool
    {
        return $this->quantity > 0;
    }

    public function getIsLowStock(): bool
    {
        return $this->quantity > 0 && $this->quantity <= self::LOW_STOCK_THRESHOLD;
    }
}

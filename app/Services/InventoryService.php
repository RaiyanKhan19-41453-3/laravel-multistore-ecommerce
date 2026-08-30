<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function getForProduct(Product $product): Collection
    {
        if ($product->isSimple()) {
            return Inventory::where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->get();
        }

        return Inventory::where('product_id', $product->id)
            ->whereNotNull('product_variant_id')
            ->get();
    }

    public function getForVariant(ProductVariant $variant): ?Inventory
    {
        return Inventory::where('product_id', $variant->product_id)
            ->where('product_variant_id', $variant->id)
            ->first();
    }

    public function getOrCreateForProduct(Product $product): Inventory
    {
        return Inventory::firstOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => 0,
                'reserved_quantity' => 0,
            ]
        );
    }

    public function getOrCreateForVariant(ProductVariant $variant): Inventory
    {
        return Inventory::firstOrCreate(
            [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
            ],
            [
                'quantity' => 0,
                'reserved_quantity' => 0,
            ]
        );
    }

    public function adjust(Inventory $inventory, string $type, int $quantity, ?string $note = null): InventoryMovement
    {
        return DB::transaction(function () use ($inventory, $type, $quantity, $note) {
            $inventory = Inventory::lockForUpdate()->find($inventory->id);

            $newQuantity = $inventory->quantity + $quantity;

            if ($newQuantity < 0) {
                throw new \InvalidArgumentException(
                    'Insufficient stock. Available: '.$inventory->quantity.', requested: '.abs($quantity)
                );
            }

            $inventory->update(['quantity' => $newQuantity]);

            return InventoryMovement::create([
                'inventory_id' => $inventory->id,
                'type' => $type,
                'quantity' => $quantity,
                'note' => $note,
                'user_id' => Auth::id(),
            ]);
        });
    }

    public function reserve(Inventory $inventory, int $quantity): void
    {
        DB::transaction(function () use ($inventory, $quantity) {
            $inventory = Inventory::lockForUpdate()->find($inventory->id);

            $available = $inventory->quantity - $inventory->reserved_quantity;

            if ($available < $quantity) {
                throw new \InvalidArgumentException(
                    'Insufficient available stock. Available: '.$available.', requested: '.$quantity
                );
            }

            $inventory->increment('reserved_quantity', $quantity);
        });
    }

    public function release(Inventory $inventory, int $quantity): void
    {
        DB::transaction(function () use ($inventory, $quantity) {
            $inventory = Inventory::lockForUpdate()->find($inventory->id);

            $releaseAmount = min($quantity, $inventory->reserved_quantity);

            if ($releaseAmount > 0) {
                $inventory->decrement('reserved_quantity', $releaseAmount);
            }
        });
    }

    public function setQuantity(Inventory $inventory, int $quantity, ?string $note = null): void
    {
        if ($quantity < 0) {
            throw new \InvalidArgumentException('Quantity cannot be negative.');
        }

        DB::transaction(function () use ($inventory, $quantity, $note) {
            $inventory = Inventory::lockForUpdate()->find($inventory->id);

            $difference = $quantity - $inventory->quantity;

            $inventory->update(['quantity' => $quantity]);

            if ($difference !== 0) {
                InventoryMovement::create([
                    'inventory_id' => $inventory->id,
                    'type' => 'adjustment',
                    'quantity' => $difference,
                    'note' => $note,
                    'user_id' => Auth::id(),
                ]);
            }
        });
    }
}

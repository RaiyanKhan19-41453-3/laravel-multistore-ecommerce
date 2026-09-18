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
    public function __construct(
        private readonly NotificationService $notifications = new NotificationService,
    ) {}

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
        $wasAvailable = $inventory->getAvailableQuantity() > 0;

        $movement = DB::transaction(function () use ($inventory, $type, $quantity, $note) {
            $inventory = Inventory::lockForUpdate()->find($inventory->id);

            $newQuantity = $inventory->quantity + $quantity;

            if ($newQuantity < 0) {
                throw new \InvalidArgumentException(
                    'Insufficient stock. Available: '.$inventory->quantity.', requested: '.abs($quantity)
                );
            }

            // Never strand live holds: reducing below reserved units
            // would push availability negative for carts and orders
            // already holding stock.
            if ($newQuantity < $inventory->reserved_quantity) {
                throw new \InvalidArgumentException(
                    'Cannot reduce below '.$inventory->reserved_quantity.' reserved unit(s). Release or fulfil those holds first.'
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

        $this->notifyIfRestocked($inventory->fresh() ?? $inventory, $wasAvailable);

        return $movement;
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

        $wasAvailable = $inventory->getAvailableQuantity() > 0;

        DB::transaction(function () use ($inventory, $quantity, $note) {
            $inventory = Inventory::lockForUpdate()->find($inventory->id);

            if ($quantity < $inventory->reserved_quantity) {
                throw new \InvalidArgumentException(
                    'Cannot set below '.$inventory->reserved_quantity.' reserved unit(s). Release or fulfil those holds first.'
                );
            }

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

        $this->notifyIfRestocked($inventory->fresh() ?? $inventory, $wasAvailable);
    }

    /**
     * Mail wishlisters when an explicit stock change brings a product back.
     * Reservation releases are excluded: they only free held units, and
     * firing on every cancellation would spam the same customers.
     */
    private function notifyIfRestocked(Inventory $inventory, bool $wasAvailable): void
    {
        if ($wasAvailable || $inventory->getAvailableQuantity() <= 0) {
            return;
        }

        $product = $inventory->product;

        if ($product && $product->is_active) {
            $this->notifications->notifyBackInStock($product);
        }
    }
}

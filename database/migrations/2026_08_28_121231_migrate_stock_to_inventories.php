<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        $products = Product::all();
        $variants = ProductVariant::all();

        foreach ($products as $product) {
            if ($product->type === 'simple') {
                DB::table('inventories')->insert([
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'quantity' => $product->quantity,
                    'reserved_quantity' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach ($variants as $variant) {
            DB::table('inventories')->insert([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => $variant->quantity,
                'reserved_quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        DB::table('inventories')->truncate();
    }
};

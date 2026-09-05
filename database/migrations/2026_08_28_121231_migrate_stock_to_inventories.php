<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

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

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('inventories')->truncate();
        Schema::enableForeignKeyConstraints();
    }
};

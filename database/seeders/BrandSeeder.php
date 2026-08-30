<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = ['Nike', 'Adidas', 'Bata'];

        foreach ($brands as $index => $name) {
            Brand::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }
    }
}

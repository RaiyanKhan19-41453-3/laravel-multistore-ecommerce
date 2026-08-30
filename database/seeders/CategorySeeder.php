<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Clothing' => ['T-Shirts', 'Hoodies', 'Jackets', 'Pants'],
            'Shoes' => ['Sneakers', 'Formal', 'Sandals'],
            'Accessories' => ['Bags', 'Hats', 'Watches'],
        ];

        $sort = 0;
        foreach ($categories as $name => $children) {
            $parent = Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
                'sort_order' => $sort++,
            ]);

            foreach ($children as $i => $childName) {
                Category::create([
                    'parent_id' => $parent->id,
                    'name' => $childName,
                    'slug' => Str::slug($childName),
                    'is_active' => true,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}

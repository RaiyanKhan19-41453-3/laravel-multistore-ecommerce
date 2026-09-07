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
            $parent = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );

            foreach ($children as $i => $childName) {
                Category::firstOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childName,
                        'is_active' => true,
                        'sort_order' => $i,
                    ]
                );
            }
        }
    }
}

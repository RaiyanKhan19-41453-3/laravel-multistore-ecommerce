<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            'Color' => ['Red', 'Blue', 'Green', 'Black', 'White', 'Gray', 'Navy', 'Brown'],
            'Size' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
            'Material' => ['Cotton', 'Polyester', 'Leather', 'Nylon', 'Rubber', 'Mesh', 'Suede'],
            'RAM' => ['4GB', '8GB', '16GB', '32GB'],
            'Storage' => ['64GB', '128GB', '256GB', '512GB', '1TB'],
        ];

        $sort = 0;
        foreach ($attributes as $name => $values) {
            $attribute = Attribute::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );

            foreach ($values as $i => $value) {
                AttributeValue::firstOrCreate(
                    ['attribute_id' => $attribute->id, 'slug' => Str::slug($value)],
                    [
                        'value' => $value,
                        'is_active' => true,
                        'sort_order' => $i,
                    ]
                );
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;

class CourierSeeder extends Seeder
{
    public function run(): void
    {
        $couriers = [
            ['name' => 'Pathao Courier', 'code' => 'pathao', 'sort_order' => 1],
            ['name' => 'Paperfly', 'code' => 'paperfly', 'sort_order' => 2],
            ['name' => 'SA Paribahan', 'code' => 'sa_paribahan', 'sort_order' => 3],
            ['name' => 'Sundarban', 'code' => 'sundarban', 'sort_order' => 4],
            ['name' => 'Ecourier', 'code' => 'ecourier', 'sort_order' => 5],
            ['name' => 'Steadfast', 'code' => 'steadfast', 'sort_order' => 6],
            ['name' => 'RedX', 'code' => 'redx', 'sort_order' => 7],
            ['name' => 'SMSA Express', 'code' => 'smsa', 'sort_order' => 8],
            ['name' => 'Aramex', 'code' => 'aramex', 'sort_order' => 9],
            ['name' => 'Other', 'code' => 'other', 'sort_order' => 99],
        ];

        foreach ($couriers as $courier) {
            Courier::updateOrCreate(
                ['code' => $courier['code']],
                $courier,
            );
        }
    }
}

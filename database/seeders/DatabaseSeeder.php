<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Store::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => config('store.name', config('app.name', 'Default Store')),
                'country' => config('store.country', 'BD'),
                'currency' => config('store.currency', 'BDT'),
                'locale' => config('store.locale', 'en'),
                'timezone' => config('store.timezone', 'Asia/Dhaka'),
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ]
        );

        User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Customer User',
                'password' => Hash::make('password'),
            ]
        );

        $this->call(PermissionSeeder::class);
        $this->call(SettingsSeeder::class);
        $this->call(BrandSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(AttributeSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(DiscountSeeder::class);
        $this->call(ShippingSeeder::class);
        $this->call(CourierSeeder::class);
    }
}

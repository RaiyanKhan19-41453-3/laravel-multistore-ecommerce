<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'domain' => null,
            'country' => 'BD',
            'currency' => 'BDT',
            'locale' => 'en',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
            'owner_user_id' => User::factory(),
        ];
    }
}

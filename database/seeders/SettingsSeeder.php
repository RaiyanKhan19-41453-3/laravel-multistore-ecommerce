<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'store.country', 'value' => config('store.country', 'BD'), 'group' => 'store'],
            ['key' => 'store.currency', 'value' => config('store.currency', 'BDT'), 'group' => 'store'],
            ['key' => 'store.locale', 'value' => config('store.locale', 'en'), 'group' => 'store'],
            ['key' => 'store.timezone', 'value' => config('store.timezone', 'Asia/Dhaka'), 'group' => 'store'],
            ['key' => 'tax.mode', 'value' => config('tax.mode', 'off'), 'group' => 'tax'],
            ['key' => 'tax.rate', 'value' => (string) config('tax.rate', 0), 'group' => 'tax'],
        ];

        foreach ($defaults as $row) {
            // Platform global defaults live on NULL store_id; per-store
            // rows (adopted or written by the admin UI) override them.
            Setting::firstOrCreate(['store_id' => null, 'key' => $row['key']], $row);
        }
    }
}

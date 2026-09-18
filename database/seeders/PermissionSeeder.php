<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Domain permissions. Suffix convention: `.view` reads, `.manage`
     * writes. A bare name covers both for its small domain.
     *
     * @return array<int, string>
     */
    public static function domainPermissions(): array
    {
        return [
            'orders.view',
            'orders.manage',
            'catalog.view',
            'catalog.manage',
            'customers',
            'discounts',
            'inventory',
            'shipping',
            'reports',
            'settings',
            'reviews',
            'pages',
            'menus',
            'homepage',
            'zatca',
            'activity',
            'audit',
            'stores',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function staffRoles(): array
    {
        $all = array_merge(self::domainPermissions(), [
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage roles',
            'manage permissions',
        ]);

        return [
            'super-admin' => $all,
            'order-manager' => [
                'orders.view',
                'orders.manage',
                'customers',
                'reviews',
                'reports',
                'activity',
                'inventory',
            ],
            'catalog-manager' => [
                'catalog.view',
                'catalog.manage',
                'inventory',
                'pages',
                'menus',
                'homepage',
            ],
            'support' => [
                'orders.view',
                'customers',
                'catalog.view',
                'reviews',
            ],
            'accountant' => [
                'reports',
                'orders.view',
                'zatca',
            ],
            // Merchant role for Shopify-style stores. Grants every
            // store-level domain but no platform powers (user/role
            // management, audit log). Store isolation itself comes from
            // the admin store context + membership, not from this role.
            'store-owner' => [
                'orders.view',
                'orders.manage',
                'catalog.view',
                'catalog.manage',
                'customers',
                'discounts',
                'inventory',
                'shipping',
                'reports',
                'settings',
                'reviews',
                'pages',
                'menus',
                'homepage',
                'zatca',
                'activity',
            ],
        ];
    }

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = array_merge(self::domainPermissions(), [
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage roles',
            'manage permissions',
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::staffRoles() as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($roleName === 'super-admin') {
                // Additive: never strip permissions a client added themselves.
                $role->givePermissionTo($rolePermissions);
            } else {
                $role->syncPermissions($rolePermissions);
            }
        }

        $testUser = User::where('email', 'test@example.com')->first();
        if ($testUser) {
            $testUser->assignRole('super-admin');
        }
    }
}

<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'admin.products.update',
            'method' => 'PUT',
            'path' => 'admin/products/1',
            'ip' => '127.0.0.1',
            'status' => 302,
            'changes' => ['name' => 'Example'],
        ];
    }
}

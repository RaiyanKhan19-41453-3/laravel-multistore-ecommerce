<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courier extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToStore);
    }

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'settings',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'encrypted:array',
        ];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}

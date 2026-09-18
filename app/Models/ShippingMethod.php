<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToStore);
    }

    protected $fillable = [
        'name',
        'description',
        'estimated_days',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'estimated_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

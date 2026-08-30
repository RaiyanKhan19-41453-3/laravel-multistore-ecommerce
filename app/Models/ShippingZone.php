<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country',
        'cities',
        'is_fallback',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'cities' => 'array',
            'is_fallback' => 'boolean',
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

    public function scopeNonFallback($query)
    {
        return $query->where('is_fallback', false);
    }

    public function containsCity(string $city): bool
    {
        if (! $this->cities) {
            return false;
        }

        $city = strtolower(trim($city));

        foreach ($this->cities as $zoneCity) {
            if (strtolower(trim($zoneCity)) === $city) {
                return true;
            }
        }

        return false;
    }

    public static function validateNoDuplicateCities(?int $exceptId = null, ?array $cities = null, bool $isFallback = false): void
    {
        if ($isFallback || empty($cities)) {
            return;
        }

        $query = static::active()->nonFallback();

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $existingZones = $query->get();

        $normalizedNew = array_map(fn ($c) => strtolower(trim($c)), $cities);

        foreach ($existingZones as $zone) {
            if (! $zone->cities) {
                continue;
            }

            $existingCityLower = array_map(fn ($c) => strtolower(trim($c)), $zone->cities);
            $duplicates = array_intersect($normalizedNew, $existingCityLower);

            if (! empty($duplicates)) {
                throw ValidationException::withMessages([
                    'cities' => 'The following cities are already assigned to another zone: '.implode(', ', $duplicates),
                ]);
            }
        }
    }

    public static function findForCity(string $city, string $country = 'Bangladesh'): ?self
    {
        $normalizedCity = strtolower(trim($city));

        $zone = static::active()->nonFallback()
            ->where('country', $country)
            ->get()
            ->first(fn ($z) => $z->containsCity($normalizedCity));

        return $zone ?? static::active()->where('is_fallback', true)->where('country', $country)->first();
    }
}

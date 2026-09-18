<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSection extends Model
{
    use HasFactory;

    public const KEYS = ['hero', 'brands', 'categories', 'featured', 'top_rated', 'spotlight', 'banners', 'perks'];

    protected $fillable = [
        'store_id',
        'key',
        'banner_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToStore);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('key');
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Banner::class);
    }
}

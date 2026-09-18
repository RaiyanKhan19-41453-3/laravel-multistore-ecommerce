<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    public const TYPES = ['category', 'brand', 'product', 'page', 'url'];

    public const CLICK_BEHAVIORS = ['navigate', 'expand'];

    public const DISPLAYS = ['auto', 'mega', 'dropdown'];

    protected $fillable = [
        'store_id',
        'parent_id',
        'title',
        'title_ar',
        'type',
        'reference_id',
        'url',
        'click_behavior',
        'display',
        'promo_image',
        'promo_title',
        'promo_link',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order')->orderBy('title');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orderBy('sort_order')->orderBy('title');
    }

    public function displayName(): string
    {
        if (app()->getLocale() === 'ar' && filled($this->title_ar)) {
            return (string) $this->title_ar;
        }

        return (string) $this->title;
    }
}

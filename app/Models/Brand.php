<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedCatalog;
use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory, HasLocalizedCatalog, SoftDeletes;

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'description',
        'description_ar',
        'logo',
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

        static::creating(function (Brand $brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function discounts(): MorphToMany
    {
        return $this->morphToMany(Discount::class, 'discountable');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

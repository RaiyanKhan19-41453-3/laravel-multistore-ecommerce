<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'currency',
        'interval',
        'trial_days',
        'features',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'trial_days' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Plan $plan) {
            if (empty($plan->slug) && ! empty($plan->name)) {
                $plan->slug = Str::slug($plan->name);
            }
        });

        // A single default plan for trial signups.
        static::saved(function (Plan $plan) {
            if ($plan->is_default) {
                static::where('id', '!=', $plan->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
